<?php

/**
 * An abstract class for defining data transfer objects (DTOs) used for validation.
 * Extend this class to define DTOs for validation rules.
 */
abstract class ValidationDTO
{
    /**
     * Define validation rules for the data transfer object.
     *
     * @return array An associative array where keys represent property names and values represent validation rules.
     */
    abstract public function rules(): array;

    /**
     * Define custom validation error messages for properties.
     *
     * @return array An associative array where keys represent validation rule names and values represent custom error messages.
     */
    public function messages(): array
    {
        return [];
    }
}
