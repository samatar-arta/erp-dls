<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nettoyage du HTML des modèles à l'enregistrement : balises actives, attributs on*, URLs javascript:.
 * Les modèles ne sont jamais compilés (ni Blade ni PHP) ; ceci est une défense en profondeur.
 */
final class HtmlSanitizer
{
    private const BALISES_INTERDITES = ['script', 'iframe', 'object', 'embed', 'applet', 'link', 'meta', 'base', 'form'];

    public static function nettoyer(string $html): string
    {
        // Balises PHP et commentaires conditionnels : jamais conservés.
        $html = (string) preg_replace('/<\?(?:php|=)?.*?\?>/is', '', $html);

        foreach (self::BALISES_INTERDITES as $balise) {
            // Balise avec contenu, puis balises orphelines (ouvrantes ou fermantes).
            $html = (string) preg_replace('#<\s*'.$balise.'\b[^>]*>.*?<\s*/\s*'.$balise.'\s*>#is', '', $html);
            $html = (string) preg_replace('#<\s*/?\s*'.$balise.'\b[^>]*>#i', '', $html);
        }

        // Attributs de gestion d'événements (onclick=, onerror=…), avec ou sans guillemets.
        $html = (string) preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        // URLs dangereuses dans href/src/action/style.
        $html = (string) preg_replace(
            '/(href|src|action|formaction|xlink:href)\s*=\s*(["\']?)\s*(?:javascript|vbscript|data\s*:\s*text\/html)[^"\'>\s]*\2/i',
            '$1=$2#$2',
            $html,
        );
        $html = (string) preg_replace('/expression\s*\(|javascript\s*:/i', '', $html);

        return $html;
    }
}
