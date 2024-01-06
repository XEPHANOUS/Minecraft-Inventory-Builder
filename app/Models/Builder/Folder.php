<?php

namespace App\Models\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    // Les attributs de base du modèle
    protected $fillable = ['name', 'user_id', 'parent_id'];

    /**
     * Relation parente (un dossier peut avoir un seul dossier parent).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * Relation enfants (un dossier peut avoir plusieurs sous-dossiers).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    /**
     * Relation avec l'utilisateur (chaque dossier appartient à un utilisateur).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
