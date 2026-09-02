<?php

namespace App\Security;

/** Politique commune appliquée à tous les nouveaux mots de passe ChirOrg. */
final class PasswordPolicy
{
    public const int MIN_LENGTH = 12;
    public const int MAX_LENGTH = 128;
    public const string REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])\S+$/';
    public const string MESSAGE = 'Le mot de passe doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial, sans espace.';

    private function __construct()
    {
    }
}
