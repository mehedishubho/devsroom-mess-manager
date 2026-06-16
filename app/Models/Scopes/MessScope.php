<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class MessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $messId = config('mess.active_mess_id');

        if ($messId !== null) {
            $builder->where($model->getTable().'.mess_id', $messId);
        }
    }
}
