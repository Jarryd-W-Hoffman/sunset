<?php

namespace Jarryd\Sunset\Integrations;

use Illuminate\Routing\Route;
use Jarryd\Sunset\Sunset;

/**
 * Optional extras when jarryd/route-notes is installed. Sunset never requires
 * it: it only writes notes in Route Notes' stored format and reads them back.
 */
class RouteNotes
{
    public const PREFIX = 'Sunset on ';

    public static function installed(): bool
    {
        return class_exists('Jarryd\RouteNotes\Note');
    }

    /**
     * Add a deprecated note describing the sunset, replacing any earlier one,
     * so route:list shows it beneath the route.
     */
    public static function addNote(Route $route, Sunset $sunset): void
    {
        $text = self::PREFIX.$sunset->date()->format('j M Y');

        if ($sunset->replacement !== null) {
            $text .= ", use {$sunset->replacement}";
        }

        $notes = array_filter(
            (array) ($route->action['notes'] ?? []),
            fn (mixed $note) => ! (is_array($note) && ($note['level'] ?? null) === 'deprecated'
                && is_string($note['text'] ?? null) && str_starts_with($note['text'], self::PREFIX)),
        );

        $route->action['notes'] = [...array_values($notes), ['text' => $text, 'level' => 'deprecated']];
    }

    /**
     * The texts of a route's deprecated notes: fluent ones, then #[Note] and
     *
     * @note ones on its controller.
     *
     * @return list<string>
     */
    public static function deprecatedNotes(Route $route): array
    {
        $notes = [];

        foreach ((array) ($route->action['notes'] ?? []) as $note) {
            if (is_array($note) && ($note['level'] ?? null) === 'deprecated' && is_string($note['text'] ?? null)) {
                $notes[] = $note['text'];
            }
        }

        $reader = ['Jarryd\RouteNotes\Support\NoteReader', 'getNotes'];
        $read = is_callable($reader) ? $reader($route->getAction('uses')) : [];

        foreach (is_array($read) ? $read : [] as $note) {
            if (is_object($note) && isset($note->level, $note->text) && $note->level instanceof \BackedEnum
                && $note->level->value === 'deprecated' && is_string($note->text)) {
                $notes[] = $note->text;
            }
        }

        return array_values(array_unique($notes));
    }
}
