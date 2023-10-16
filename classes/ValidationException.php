<?php

/**
 * Class ValidationException
 *
 * Represents an exception thrown when validation errors occur.
 */
class ValidationException extends Exception
{
    /**
     * @var array An array containing validation errors.
     */
    private $errors;

    /**
     * ValidationException constructor.
     *
     * @param array $errors An array of validation errors.
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception used for the exception chaining.
     */
    public function __construct($errors = [], $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->setErrors($errors);
    }

    /**
     * Get the validation errors.
     *
     * @return array An array of validation errors.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Set the validation errors.
     *
     * @param array $errors An array of validation errors.
     */
    private function setErrors($errors)
    {
        $this->errors = $errors;
    }
}