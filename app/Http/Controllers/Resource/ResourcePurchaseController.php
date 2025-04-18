<?php

namespace App\Http\Controllers\Resource;

use App\Http\Controllers\Controller;
use App\Models\Payment\Gift;
use App\Models\Payment\Payment;
use App\Models\Resource\Resource;
use App\Models\UserLog;
use App\Payment\Events\PaymentCancel;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResourcePurchaseController extends Controller
{
    /**
     * Permet d'afficher la page pour acheter une resource
     *
     * @param Resource $resource
     * @return \Illuminate\Contracts\Foundation\Application|Factory|View|Application|RedirectResponse
     */
    public function index(Resource $resource): Application|View|Factory|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        // Si l'utilisateur à déjà accès à la resource ou que le prix est à 0, alors on retourne en arrière
        if ($resource->price == 0 || (user()->hasAccess($resource, false) && !user()->isAdmin())) {
            return Redirect::route('resources.view', ['slug' => Str::slug($resource->name), 'resource' => $resource->id]);
        }

        if ($resource->id == env('ZMENUPLUS_RESOURCE_ID')) {
            return Redirect::route('resources.view', ['slug' => Str::slug($resource->name), 'resource' => $resource->id]);
        }

        $paymentInfo = $resource->user->paymentInfo;
        if ($paymentInfo->sk_live == null && $paymentInfo->paypal_email == null) {
            return Redirect::route('resources.view', ['slug' => Str::slug($resource->name), 'resource' => $resource->id]);
        }

        // Sinon, on affiche la page d'achat
        return view('resources.purchase.checkout', [
            'paymentInfo' => $paymentInfo,
            'price' => $resource->price,
            'currency' => $paymentInfo->currency->currency,
            'confirmUrl' => route('resources.purchase.session', ['resource' => $resource->id]),
            'name' => $resource->name,
            'enableGift' => true,
            'resource' => $resource,
            'contentId' => $resource->id,
            'contentType' => Resource::class,
        ]);
    }

    /**
     * Commencer l'achat d'une resource
     *
     * @param Request $request
     * @param Resource $resource
     * @return mixed
     * @throws ValidationException
     */
    public function store(Request $request, Resource $resource): mixed
    {
        $this->validate($request, [
            'paymentMethod' => 'required',
            'gift' => ['nullable', 'string', 'max:20', 'min:3', 'regex:/^[a-zA-Z0-9_]+$/u'],
            'terms' => ['accepted'],
        ]);

        $gift = null;

        $paymentMethod = 'stripe';
        try {
            $paymentMethod = $request['paymentMethod'][0];
            if ($paymentMethod != 'stripe' && $paymentMethod != 'paypal') {
                return Redirect::back()->with('toast', createToast('error', 'Error', 'An internal error occurred during payment', 10000));
            }
        } catch (Exception $exception) {
            return Redirect::back()->with('toast', createToast('error', 'Error', 'An internal error occurred during payment', 10000));
        }

        if (isset($request['gift'])) {
            $gift = Gift::where('code', $request['gift'])->first();
        }

        $user = user();
        $payment = Payment::makeDefault($user, $resource->price, Payment::TYPE_RESOURCE, $resource->id, $resource->user->paymentInfo->currency_id, $paymentMethod, $gift?->id);

        return paymentManager()->startPayment($request, $resource, $paymentMethod, $payment, $gift);
    }

    /**
     * Affiche la page de succès lors de l'achat
     *
     * @param Payment $payment
     * @return \Illuminate\Contracts\Foundation\Application|Factory|View|Application|RedirectResponse
     */
    public function success(Payment $payment): Application|View|Factory|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {

        if (!$payment->isPaid()) {
            if ($payment->status != Payment::STATUS_UNPAID) {
                return Redirect::route('resources.index');
            }
            $payment->update(['status' => Payment::STATUS_PENDING]);
        }


        $gift = $payment->gift;
        $contentPrice = $payment->price;
        $price = $payment->price;
        $giftReduction = 0;
        $currency = $payment->currency->currency;
        $name = $payment->getPaymentContentNameFormatted();

        switch ($payment->type) {
            case Payment::TYPE_RESOURCE:
            {
                $contentPrice = $payment->resource->price;
                break;
            }
            case Payment::TYPE_NAME_COLOR:
            {
                $contentPrice = $payment->nameColor->price;
                break;
            }
        }

        if (isset($gift)) {
            $giftReduction = ($contentPrice * $gift->reduction) / 100;
            $price = $contentPrice - $giftReduction;
        }

        return view('resources.purchase.success', [
            'payment' => $payment,
            'price' => $price,
            'name' => $name,
            'currency' => $currency,
            'gift' => $gift,
            'giftReduction' => $giftReduction,
            'contentPrice' => $contentPrice,
        ]);
    }


    /**
     * Quand un paiement est annulé
     *
     * @param Payment $payment
     * @return \Illuminate\Contracts\Foundation\Application|Factory|View|Application|RedirectResponse
     */
    public function cancel(Payment $payment): Application|View|Factory|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {

        if (!$payment->isPaid()) {
            if ($payment->status != Payment::STATUS_UNPAID) {
                return Redirect::route('resources.index');
            }
            $payment->update(['status' => Payment::STATUS_CANCEL]);
            event(new PaymentCancel($payment));
        }

        userLogOffline($payment->user_id, "Vient d'annuler le paiement $payment->payment_id.$payment->id", UserLog::COLOR_DANGER, UserLog::ICON_TRASH);

        return view('resources.purchase.cancel');
    }

    /**
     * Affiche les ressources achetées par l'utilisateur
     *
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function purchased(): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {

        $accesses = user()->accesses;
        return view('resources.users.purchased', ['accesses' => $accesses]);
    }

    /**
     * Afficher le détail d'un paiement
     *
     * @param Payment $payment
     * @return \Illuminate\Contracts\Foundation\Application|Factory|View|Application
     */
    public function paymentDetails(Payment $payment): Application|View|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $currency = "eur";
        $name = "";
        $gift = $payment->gift;
        $contentPrice = $payment->price;
        $price = $payment->price;
        $giftReduction = 0;
        if ($payment->type == Payment::TYPE_RESOURCE) {
            $resource = $payment->resource;
            $contentPrice = $resource->price;
            $currency = $payment->currency->currency;
            $name = $resource->name;
        }

        if (isset($gift)) {
            $giftReduction = ($contentPrice * $gift->reduction) / 100;
            $price = $contentPrice - $giftReduction;
        }

        return view('resources.users.details', [
            'payment' => $payment,
            'price' => $price,
            'name' => $name,
            'currency' => $currency,
            'gift' => $gift,
            'giftReduction' => $giftReduction,
            'contentPrice' => $contentPrice,
        ]);
    }

}
