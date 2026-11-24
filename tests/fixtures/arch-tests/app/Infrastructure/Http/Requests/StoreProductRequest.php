<?php

namespace ArchTestsFixture\Infrastructure\Http\Requests;

class StoreProductRequest extends BaseRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return ['name' => 'required'];
    }
}
