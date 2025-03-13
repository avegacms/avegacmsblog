<?php
declare(strict_types=1);

namespace AvegaCmsBlog\Exception;

use Exception;

class ValidationException extends Exception {
    protected array $errors;

    public function __construct(array $errors, $message = "Ошибка валидации", $code = 0, Exception $previous = null) {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
