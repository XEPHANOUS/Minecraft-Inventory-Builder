<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use App\Models\Builder\ActionType;
use App\Models\Builder\ButtonType;
use App\Models\Builder\Folder;
use App\Models\Builder\Inventory;
use App\Models\Builder\InventoryButton;
use App\Models\Builder\InventoryButtonAction;
use App\Models\Builder\Item;
use App\Models\MinecraftVersion;
use App\Models\UserLog;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BuilderInventoryController extends Controller
{

    /**
     * Récupérer la liste des inventaires d'un dossier
     *
     * @param Folder $folder
     * @return bool|string
     */
    public function inventories(Folder $folder): bool|string
    {

        $user = user();
        if ($folder->user_id != $user->id && !$user->isAdmin()) {
            return json_encode(['result' => 'error', 'toast' => createToast('error', 'Error', 'Cannot use this folder.', 5000)]);
        }

        $inventories = Inventory::where('folder_id', $folder->id)->get();
        return json_encode(['result' => 'success', 'inventories' => $inventories,]);
    }

    /**
     * Edit an inventory
     *
     * @param Inventory $inventory
     * @return Factory|\Illuminate\Foundation\Application|View|RedirectResponse|Application
     */
    public function edit(Inventory $inventory): Factory|\Illuminate\Foundation\Application|View|RedirectResponse|Application
    {

        $user = user();
        if ($inventory->user_id != $user->id && !$user->role->isModerator()) {
            return Redirect::route('home');
        }

        $sounds = Cache::remember('xsound:values', 86400, function () {
            $content = file_get_contents('https://raw.githubusercontent.com/CryptoMorin/XSeries/refs/heads/master/core/src/main/java/com/cryptomorin/xseries/XSound.java');
            preg_match_all('/^\s{4}([A-Z_]+)(?=\(|,)/m', $content, $matches);
            return $matches[1];
        });

        $versions = MinecraftVersion::all();
        $inventory = $inventory->load(['buttons', 'buttons.head', 'buttons.item', 'buttons.actions']);

        $buttonTypes = ButtonType::with('contents')->get();
        $actions = ActionType::all();
        $actions = $actions->load('contents');

        return view('builder.inventory', ['inventory' => $inventory, 'versions' => $versions, 'buttonTypes' => $buttonTypes, 'sounds' => $sounds, 'actions' => $actions,]);
    }

    /**
     * Permet de renommer un inventaire
     *
     * @param Request $request
     * @param Inventory $inventory
     * @return bool|string
     */
    public function rename(Request $request, Inventory $inventory): bool|string
    {

        $rules = ['file_name' => ['required', 'string', 'max:100', 'min:3', 'regex:/^[a-zA-Z0-9\-_]{3,100}$/'],];


        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['result' => 'error', 'toast' => createToast('error', 'Validation Error', $validator->errors()->first('file_name'), 5000)], 422);
        }

        $user = user();
        if ($inventory->user_id != $user->id && !$user->isAdmin()) {
            return json_encode(['result' => 'error', 'toast' => createToast('error', 'Error', 'Cannot use this folder.', 5000)]);
        }

        $inventory->update($request->all());

        userLog("Vient de renommer l'inventaire $inventory->file_name.$inventory->id", UserLog::COLOR_SUCCESS, UserLog::ICON_EDIT);

        return json_encode(['result' => 'success', 'toast' => createToast('success', 'Success', 'Inventory successfully renamed.', 5000)]);
    }

    /**
     * Permet de modifier un inventaire
     *
     * @param Request $request
     * @param Inventory $inventory
     * @return bool|string
     */
    public function update(Request $request, Inventory $inventory): bool|string
    {

        $validatedData = $request->validate(['name' => 'nullable|string|max:500|min:0', 'file_name' => 'required|string|max:100|min:3', 'size' => 'required|integer', 'update_interval' => 'required|integer', 'clear_inventory' => 'required']);

        $user = user();
        if ($inventory->user_id != $user->id && !$user->isAdmin()) {
            return json_encode(['result' => 'error', 'toast' => createToast('error', 'Error', 'Cannot use this inventory.', 5000)]);
        }

        $inventory->update(['name' => $validatedData['name'], 'file_name' => $validatedData['file_name'], 'size' => $validatedData['size'], 'update_interval' => $validatedData['update_interval'], 'clear_inventory' => $validatedData['name'] === 'true',]);

        $this->updateButton($request, $inventory);

        userLog("Vient de modifier l'inventaire $inventory->file_name.$inventory->id", UserLog::COLOR_SUCCESS, UserLog::ICON_EDIT);

        return json_encode(['result' => 'success']);

    }

    /**
     * Permet de mettre à jour les boutons de l'inventaire
     *
     * @param Request $request
     * @param Inventory $inventory
     * @return void
     */
    private function updateButton(Request $request, Inventory $inventory): void
    {
        $itemIds = array_column($request['slot'], 'item_id');
        $itemIds = array_unique(array_filter($itemIds));

        $typeIds = array_column($request['slot'], 'type_id');
        $typeIds = array_unique(array_filter($typeIds));


        $items = Item::findMany($itemIds)->keyBy('id');
        $buttonTypes = ButtonType::findMany($typeIds)->keyBy('id');

        $slotsToKeep = array_filter(array_column($request['slot'], 'slot'), function ($value) {
            return ($value !== null && $value !== false);
        });

        foreach ($request['slot'] as $slot) {
            if (empty($slot) || !isset($items[$slot['item_id']])) continue;

            $currentSlot = $slot['slot'];
            $page = filter_var($slot['page'], FILTER_VALIDATE_INT) !== false ? $slot['page'] : 1;
            $itemId = $slot['item_id'];

            $name = Str::limit($slot['name'], 255);
            $name = empty($name) ? "btn-$currentSlot" : str_replace(" ", "_", $name);

            $item = $items[$itemId];

            $typeId = $buttonTypes[$slot['type_id']]?->id ?? 1;

            $button = InventoryButton::updateOrCreate(['inventory_id' => $inventory->id, //
                'slot' => $currentSlot, //
                'page' => $page //
            ], ['item_id' => $item->id, 'amount' => max(1, min(64, $slot['amount'])), //
                'type_id' => $typeId,  //
                'head_id' => $this->cleanSlotValue($slot, 'head_id', null, 'int'), //
                'name' => $name,  //
                'display_name' => Str::limit($this->cleanSlotValue($slot, 'display_name'), 65535), //
                'lore' => Str::limit($this->cleanSlotValue($slot, 'lore'), 65535), //
                'is_permanent' => $this->getBoolean($slot, 'is_permanent'), //
                'close_inventory' => $this->getBoolean($slot, 'close_inventory'), //
                'refresh_on_click' => $this->getBoolean($slot, 'refresh_on_click'),  //
                'update_on_click' => $this->getBoolean($slot, 'update_on_click'), //
                'update' => $this->getBoolean($slot, 'update'), //
                'glow' => $this->getBoolean($slot, 'glow'), //
                'model_id' => $this->cleanSlotValue($slot, 'model_id', 0, 'int'), //
                'button_data' => $this->cleanSlotValue($slot, 'button_data'), //
                // 'messages' => Str::limit($this->cleanSlotValue($slot, 'messages'), 65535),
                // 'sound' => $this->cleanSlotValue($slot, 'sound'),
                // 'volume' => $this->cleanSlotValue($slot, 'volume', 1),
                // 'pitch' => $this->cleanSlotValue($slot, 'pitch', 1),
                // 'commands' => Str::limit($this->cleanSlotValue($slot, 'commands'), 65535),
                // 'console_commands' => Str::limit($this->cleanSlotValue($slot, 'console_commands'), 65535),
            ]);

            $this->updateActions($button, $slot);
        }

        InventoryButton::where('inventory_id', $inventory->id)->whereNotIn('slot', $slotsToKeep)->delete();
    }

    function cleanSlotValue($slot, $key, $defaultValue = null, $type = null)
    {
        $value = $defaultValue;
        if (isset($slot[$key]) && $slot[$key] !== "null" && trim($slot[$key]) !== "") {
            $value = trim($slot[$key]);
            if ($type === 'int') {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false) {
                    return $defaultValue;
                }
            }
        }
        return $value;
    }

    function getBoolean($array, $key, $defaultValue = false)
    {

        if (!isset($array[$key])) return $defaultValue;

        $value = strtolower($array[$key]);
        if ($value == 'true') {
            return true;
        } elseif ($value == 'false') {
            return false;
        }

        return $defaultValue;
    }

    /**
     * Supprimer un inventaire
     *
     * @param Inventory $inventory
     * @return bool|string
     */
    public function delete(Inventory $inventory): bool|string
    {

        $user = user();
        if ($inventory->user_id != $user->id && !$user->isAdmin()) {
            return json_encode(['result' => 'error', 'toast' => createToast('error', 'Error', 'Cannot use this folder.', 5000)]);
        }

        $inventory->delete();

        userLog("Vient de supprimer l'inventaire $inventory->file_name.$inventory->id", UserLog::COLOR_DANGER, UserLog::ICON_TRASH);

        return json_encode(['result' => 'success', 'toast' => createToast('success', 'Success', 'Inventory successfully deleted.', 5000)]);
    }

    public function copy(Inventory $inventory): bool|string
    {
        $user = user();
        if ($inventory->user_id != $user->id && !$user->isAdmin()) {
            return json_encode(['result' => 'error', 'toast' => createToast('error', 'Error', 'Cannot use this folder.', 5000)]);
        }

        $newInventory = $inventory->replicate();
        $newInventory->save();

        foreach ($inventory->buttons as $button) {
            $newButton = $button->replicate();
            $newButton->inventory_id = $newInventory->id;
            $newButton->save();
        }

        userLog("Vient de copier l'inventaire $inventory->file_name.$inventory->id", UserLog::COLOR_SUCCESS, UserLog::ICON_CODE);

        return json_encode(['result' => 'success', 'inventory' => $newInventory, 'toast' => createToast('success', 'Success', 'Inventory successfully copied.', 5000)]);
    }

    private function updateActions($button, $slot): void
    {
        $actions = $slot['actions'] ?? [];
        $actionToKeep = [];

        $existingActions = InventoryButtonAction::where('inventory_button_id', $button->id)->get()->groupBy('inventory_action_type_id'); // Regrouper par type d'action

        foreach ($actions as $action) {
            $actionTypeId = $action['inventory_action_type_id'] ?? -1;
            if ($actionTypeId === -1) continue;
            $id = $action['id'] ?? null;

            $toUpdate = ['inventory_button_id' => $button->id, 'inventory_action_type_id' => $actionTypeId, 'data' => Str::limit($action['data'], 65535),];

            if ($id) {
                $inventoryAction = InventoryButtonAction::updateOrCreate(['id' => $id, 'inventory_button_id' => $button->id], $toUpdate);
            } else {
                if (!empty($existingActions[$actionTypeId]) && $existingActions[$actionTypeId]->isNotEmpty()) {
                    $inventoryAction = $existingActions[$actionTypeId]->shift();
                    $inventoryAction->update($toUpdate);
                } else {
                    $inventoryAction = InventoryButtonAction::create($toUpdate);
                }
            }

            $actionToKeep[] = $inventoryAction->id;
        }

        InventoryButtonAction::where('inventory_button_id', $button->id)->whereNotIn('id', $actionToKeep)->delete();
    }

    /**
     * Permet de créer un inventaire
     *
     * @param Request $request
     * @param Folder $folder
     * @return string
     */
    public function create(Request $request, Folder $folder): string
    {
        $validatedData = $request->validate(['name' => 'nullable|string|max:500|min:0', 'file_name' => 'required|string|max:100|min:3', 'size' => 'required|integer', 'update_interval' => 'required|integer', 'clear_inventory' => 'required']);

        $user = user();
        if ($folder->user_id != $user->id && !$user->isAdmin()) {
            return json_encode(['result' => 'error', 'toast' => createToast('error', 'Error', 'Cannot use this folder.', 5000)]);
        }

        $counts = Inventory::where('user_id', $user->id)->count();
        if ($counts >= $user->role->max_inventories) {
            return json_encode(['result' => 'error', 'toast' => createToast('error', 'Error', 'You cannot create a new inventory, please upgrade your account.', 5000)]);
        }

        if ($validatedData['size'] % 9 != 0) {
            return json_encode(['result' => 'error', 'toast' => createToast('error', 'Error', 'Error with your inventory size.', 5000)]);
        }

        $inventory = Inventory::create(['name' => $validatedData['name'], 'file_name' => $validatedData['file_name'], 'size' => $validatedData['size'], 'update_interval' => $validatedData['update_interval'], 'clear_inventory' => $validatedData['name'] === 'true', 'user_id' => $user->id, 'folder_id' => $folder->id, 'inventory_visibility_id' => 1,]);
        userLog("Vient de créer l'inventaire $inventory->file_name.$inventory->id", UserLog::COLOR_SUCCESS, UserLog::ICON_ADD);

        return json_encode(['result' => 'success', 'inventory' => $inventory, 'toast' => createToast('success', 'Success', 'InventoryBuilder successfully created.', 5000)]);
    }

}
