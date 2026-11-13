<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case Validated = 'validated';
    case Cancelled = 'cancelled';
    case Exported = 'exported';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Création',
            self::Updated => 'Modification',
            self::Deleted => 'Suppression',
            self::Restored => 'Restauration',
            self::Login => 'Connexion',
            self::Logout => 'Déconnexion',
            self::LoginFailed => 'Échec de connexion',
            self::Validated => 'Validation',
            self::Cancelled => 'Annulation',
            self::Exported => 'Export',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
