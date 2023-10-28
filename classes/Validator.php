<?php

/**
 *  The Validator class provides a flexible and extensible way to validate data based on specified rules.
 */
class Validator
{
    /**
     * @var ValidationDTO|null The data transfer object containing validation rules.
     */
    private $dto;

    /**
     * @var array The incoming request data.
     */
    private $requestData;

    /**
     * @var bool Flag to determine if validation should consider only request data.
     */
    private $onlyRequestData;

    /**
     * @var string|null The name of the currently validated property.
     */
    private $propertyName;

    /**
     * @var mixed The value of the currently validated property.
     */
    private $value;

    /**
     * @var bool Flag to indicate if the property is required.
     */
    private $isRequired;

    /**
     * @var array Validated data.
     */
    private $validated = [];

    /**
     * @var array Validation errors.
     */
    private $errors = [];

    /**
     * Validator constructor.
     */
    public function __construct()
    {
        $this->setOnlyRequest(false);
        $this->setRequestData(json_decode(file_get_contents('php://input')));
    }

    /**
     * Validate DTO object properties according to rules inside the DTO.
     *
     * @param ValidationDTO $dto The Data Transfer Object with validation rules.
     */
    public function validate(ValidationDTO $dto)
    {
        $this->dto = $dto;
        $this->resetValidated();
        $this->resetErrors();

        if (count($this->dto->rules()) > 0) {
            foreach ($this->dto->rules() as $propertyName => $rule) {
                $value = $this->dto->$propertyName ?? null;
                $this->validateSingle($propertyName, $value, $rule);
            }
        }

        if ($this->fails()) {
            $this->resetValidated();
        }
    }

    /**
     * Determine if the data fails the validation rules.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return count($this->getErrors()) > 0;
    }


    /**
     * Retrieve validated data.
     *
     * @return array An array of validated data.
     */
    public function getValidated(): array
    {
        if ($this->containsOnlyRequestData() && count($this->errors) > 0) {
            return array_filter($this->validated, function ($k) {
                return $this->requestContains($k);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $this->validated;
    }

    /**
     * Retrieve validation error messages for DTO properties
     *
     * @return array
     */
    public function getErrors(): array
    {
        if ($this->containsOnlyRequestData() && count($this->errors) > 0) {
            return array_filter($this->errors, function ($k) {
                return $this->requestContains($k);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $this->errors;
    }

    /**
     * Determine if validation contains only input request data
     *
     * @return mixed
     */
    public function containsOnlyRequestData()
    {
        return $this->onlyRequestData;
    }

    /**
     * Set whether validation should consider only the request data.
     *
     * @param bool $value If true, validation will only consider request data; otherwise, it will consider all data.
     *
     * @return Validator Returns the current Validator instance to allow for method chaining.
     */
    public function setOnlyRequest($value): Validator
    {
        $this->onlyRequestData = (bool)$value;
        return $this;
    }

    /**
     * Add the currently validated property to the validated data.
     *
     * @return void
     */
    private function addToValidated()
    {
        $this->validated[$this->propertyName] = $this->value;
    }

    /**
     * Add an error message for the current property to the list of validation errors.
     *
     * @param string $message The error message to add.
     */
    private function addToErrors($message)
    {
        $this->errors[$this->propertyName][] = $message;
    }

    /**
     * Check if the request data contains a specific key.
     *
     * @param mixed $key The key to check for in the request data.
     *
     * @return bool Returns true if the request data contains the specified key, false otherwise.
     */
    private function requestContains($key): bool
    {
        return in_array($key, array_keys($this->requestData));
    }

    /**
     * Set the incoming request data.
     *
     * @param mixed $data The incoming request data, typically from 'php://input'.
     * @return void
     */
    private function setRequestData($data)
    {
        $this->requestData = (array)$data;
    }

    /**
     * Reset the array of validated data.
     *
     * @return void
     */
    private function resetValidated()
    {
        $this->validated = [];
    }

    /**
     * Reset the array of error data.
     *
     * @return void
     */
    private function resetErrors()
    {
        $this->errors = [];
    }

    /**
     * Validate a single property according to its specified rules.
     *
     * @param string $propertyName The name of the property being validated.
     * @param mixed $value The value of the property being validated.
     * @param string $rules The validation rules for the property (e.g., "required|min:2|max:10").
     */
    private function validateSingle($propertyName, $value, $rules)
    {
        // assign
        $this->propertyName = $propertyName;
        $this->value = $value;

        // explode rules
        $rules = $this->sanitizeRule($rules);
        $rulesArray = explode('|', $rules);
        $this->isRequired = in_array('required', $rulesArray);

        foreach ($rulesArray as $rule) {
            $position = strpos($rule, ':');
            if ($position !== false) {
                $ruleName = substr($rule, 0, $position);
                $ruleParameter = substr($rule, $position + 1) ?? null;
            } else {
                $ruleName = $rule;
                $ruleParameter = null;
            }

            $this->callValidationMethod($ruleName, $ruleParameter);
        }
    }

    /**
     * Call a validation method for the specified rule.
     *
     * @param string $ruleName The name of the validation rule.
     * @param mixed|null $ruleParameter The parameter for the validation rule, if applicable.
     */
    private function callValidationMethod($ruleName, $ruleParameter)
    {
        if (!method_exists($this, $ruleName)) {
            throw new BadMethodCallException("Validation rule $ruleName doesn't exist.");
        }

        $reflectionMethod = new ReflectionMethod($this, $ruleName);
        $reflectionParameters = $reflectionMethod->getParameters();
        $reflectionParameter = $reflectionParameters[0] ?? null;
        $ruleParameter = $ruleParameter ?? ($reflectionParameter && $reflectionParameter->isDefaultValueAvailable(
        ) ? $reflectionParameter->getDefaultValue() : null);

        if (
            (count($reflectionParameters) > 0 && empty($ruleParameter)) ||
            (count($reflectionParameters) <= 0 && !empty($ruleParameter))
        ) {
            throw new BadMethodCallException(
                "Validation rule $ruleName expected " . count(
                    $reflectionParameters
                ) . " arguments, " . ($ruleParameter ? 1 : 0) . " provided."
            );
        }

        if ($ruleParameter) {
            $isValid = $this->$ruleName($ruleParameter);
        } else {
            $isValid = $this->$ruleName();
        }

        if ($isValid) {
            $this->addToValidated();
        } elseif ($this->isRequired || $this->requestContains($this->propertyName)) {
            $this->addToErrors($this->getFormattedMessage($ruleName, $ruleParameter));
        }
    }

    /**
     * Get validation error messages.
     *
     * @return array An array of error messages.
     */
    private function messages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'max' => 'The :attribute must not be greater than :parameter.',
            'min' => 'The :attribute must not be smaller than :parameter.',
            'date' => 'The :attribute is not a valid date.',
            'isISO8601' => 'The :attribute does not match the isISO8601 format.',
            'after' => 'The :attribute must be a date after :parameter.',
            'in' => 'The selected :attribute is invalid.',
            'hex_color' => 'The :attribute is not valid hex color',
        ];
    }

    /**
     * Get a formatted validation error message for a specific rule.
     *
     * @param string $rule The name of the validation rule.
     * @param mixed $parameter The parameter associated with the rule, if applicable.
     * @return string The formatted error message.
     */
    private function getFormattedMessage($rule, $parameter)
    {
        $errorMessage = $this->dto->messages()[$rule] ?? $this->messages()[$rule];
        $errorMessage = str_replace(':attribute', $this->propertyName, $errorMessage);
        return str_replace(':parameter', $parameter ?? '', $errorMessage);
    }

    /**
     * Sanitize a validation rule by replacing placeholders with corresponding DTO values.
     *
     * This method replaces placeholders in a validation rule, enclosed in curly braces { }, with
     * corresponding values from the Data Transfer Object (DTO).
     *
     * @param string $rule The validation rule to sanitize.
     *
     * @return string The sanitized validation rule.
     */
    private function sanitizeRule($rule)
    {
        $matches = [];
        if (preg_match('/\{([^{}]+)}/', $rule, $matches)) {
            if (isset($matches[1])) {
                $rule = str_replace('{' . $matches[1] . '}', $this->dto->{$matches[1]} ?? '', $rule);
            }
        }

        return $rule;
    }

    /**
     * Check if a property is required.
     *
     * @return bool Returns true if the property is not empty, false otherwise.
     */
    private function required(): bool
    {
        return !empty($this->value);
    }

    /**
     * Validate if the value is a valid maximum length.
     *
     * @param int|string $value The maximum length to check against.
     * @return bool Returns true if the value length is less than or equal to the provided maximum length, false otherwise.
     */
    private function max($value): bool
    {
        return strlen((string)$this->value) <= intval($value);
    }

    /**
     * Validate if the value is a valid minimum length.
     *
     * @param int|string $value The minimum length to check against.
     * @return bool Returns true if the value length is greater than or equal to the provided minimum length, false otherwise.
     */
    private function min($value): bool
    {
        return strlen($this->value) >= intval($value);
    }

    /**
     * Validate if the value is a valid date.
     *
     * @return bool Returns true if the value is a valid date, false otherwise.
     */
    private function date(): bool
    {
        return strtotime((string)$this->value) !== false;
    }

    /**
     * Validate if the value matches the ISO8601 date format.
     *
     * @return bool Returns true if the value is in ISO8601 date format, false otherwise.
     */
    private function isISO8601(): bool
    {
        return preg_match(
            '/^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})(Z|[+-]\d{2}:\d{2})$/',
            $this->value
        );
    }

    /**
     * Validate if the value is a date after a given date.
     *
     * @param string $date The date to compare against.
     * @return bool Returns true if the value is a date after the provided date, false otherwise.
     */
    private function after($date): bool
    {
        $timestamp1 = strtotime((string)$this->value);
        $timestamp2 = strtotime($date);

        return $timestamp1 !== false && $timestamp2 !== false && $timestamp1 > $timestamp2;
    }

    /**
     * Validate if the value is one of the specified values.
     *
     * @param string $values A comma-separated list of valid values.
     * @return bool Returns true if the value is in the list of valid values, false otherwise.
     */
    private function in($values): bool
    {
        $values = explode(',', $values);
        return in_array($this->value, $values);
    }

    /**
     * Validate if the value is a valid hexadecimal color code.
     *
     * @return bool Returns true if the value is a valid hexadecimal color code, false otherwise.
     */
    private function hex_color(): bool
    {
        return preg_match('/^#?([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', (string)$this->value);
    }
}
