<?php

namespace App\Support;

use OpenSpout\Writer\XLSX\Validation\DataValidationRuleInterface;
use OpenSpout\Writer\XLSX\Validation\SerializedValidationRule;
use OpenSpout\Writer\XLSX\Validation\ValidationRuleType;

final readonly class SpreadsheetListRule implements DataValidationRuleInterface
{
    public function __construct(private string $formula) {}

    public function getValidationRuleType(): ValidationRuleType
    {
        return ValidationRuleType::List;
    }

    public function serialize(): SerializedValidationRule
    {
        return new SerializedValidationRule(type: 'list', operator: null, formula1: htmlspecialchars($this->formula, ENT_XML1));
    }
}
