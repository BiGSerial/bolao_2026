<?php

namespace App\Enums;

enum LegalDocumentType: string
{
    case Eula = 'EULA';
    case PrivacyPolicy = 'PRIVACY_POLICY';
    case Disclaimer = 'DISCLAIMER';
    case ConfidentialityPolicy = 'CONFIDENTIALITY_POLICY';

    public function label(): string
    {
        return match ($this) {
            self::Eula => 'Termos de Uso',
            self::PrivacyPolicy => 'Politica de Privacidade',
            self::Disclaimer => 'Termo de Responsabilidade',
            self::ConfidentialityPolicy => 'Politica de Confidencialidade',
        };
    }
}
