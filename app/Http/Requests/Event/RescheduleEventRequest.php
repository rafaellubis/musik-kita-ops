<?php

namespace App\Http\Requests\Event;

class RescheduleEventRequest extends StoreEventRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['type']);

        return $rules;
    }
}
