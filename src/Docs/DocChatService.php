<?php

declare(strict_types=1);

/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

namespace Vtinnovations\Draggo\Docs;

use Vtinnovations\Draggo\Ai\AiClientInterface;

/**
 * Grounded documentation chatbot. Retrieves the most relevant doc sections for a
 * question (lightweight keyword scoring — the doc set is small enough to need no
 * vector store), feeds ONLY those into the system prompt, and instructs the model
 * to answer strictly from them (else say it doesn't know). So the bot can't make
 * up Draggo features. Reuses the configured AI client (BYOK) — no extra key.
 */
final class DocChatService
{
    private const MAX_SECTIONS = 8;

    public function __construct(
        private readonly AiClientInterface $ai,
        private readonly DocGenerator $docs,
    ) {
    }

    public function isReady(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * Answer the latest question given the chat history.
     *
     * @param list<array{role:string,content:string}> $history
     * @return array{text:string,sources:list<string>}
     */
    public function answer(array $history, string $lang = 'de'): array
    {
        $question = '';
        for ($i = \count($history) - 1; $i >= 0; --$i) {
            if (($history[$i]['role'] ?? '') === 'user') {
                $question = trim((string) ($history[$i]['content'] ?? ''));
                break;
            }
        }
        $sections = $this->docs->sections($lang);
        $relevant = $this->retrieve($question, $sections);

        $context = '';
        $sources = [];
        foreach ($relevant as $s) {
            $context .= "### " . $s['title'] . " (" . $s['cat'] . ")\n" . $s['body'] . "\n\n";
            $sources[] = $s['title'];
        }

        $messages = [];
        foreach ($history as $m) {
            $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($m['content'] ?? ''));
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => [['type' => 'text', 'text' => $text]]];
            }
        }
        if ($messages === []) {
            return ['text' => '', 'sources' => []];
        }

        $result = $this->ai->chat($this->system($context, $lang), $messages, []);

        return ['text' => trim($result->text), 'sources' => $sources];
    }

    /**
     * Top sections for the question by keyword overlap. Falls back to the guides
     * + a short element index when nothing matches, so the bot always has the map.
     *
     * @param list<array{id:string,title:string,cat:string,keywords:string,body:string}> $sections
     * @return list<array{id:string,title:string,cat:string,keywords:string,body:string}>
     */
    private function retrieve(string $question, array $sections): array
    {
        $tokens = array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)) ?: [],
            static fn (string $t): bool => mb_strlen($t) >= 3,
        )));

        $scored = [];
        foreach ($sections as $i => $s) {
            $hay = mb_strtolower($s['title'] . ' ' . $s['keywords'] . ' ' . $s['cat'] . ' ' . $s['body']);
            $score = 0;
            foreach ($tokens as $t) {
                if (str_contains($hay, $t)) {
                    // Title/keyword hits weigh more than body hits.
                    $score += str_contains(mb_strtolower($s['title'] . ' ' . $s['keywords']), $t) ? 3 : 1;
                }
            }
            if ($score > 0) {
                $scored[] = ['s' => $s, 'score' => $score, 'i' => $i];
            }
        }

        if ($scored === []) {
            // No match → give the guides + a compact element index as the map.
            $guides = array_values(array_filter($sections, static fn ($s): bool => $s['cat'] === 'Guide'));
            $index = array_values(array_filter($sections, static fn ($s): bool => str_starts_with($s['cat'], 'Element')));
            $names = array_map(static fn ($s): string => $s['title'], $index);
            $guides[] = ['id' => 'index', 'title' => 'Element-Übersicht', 'cat' => 'Guide', 'keywords' => '', 'body' => 'Verfügbare Elemente: ' . implode(', ', $names) . '.'];

            return \array_slice($guides, 0, self::MAX_SECTIONS);
        }

        usort($scored, static fn ($a, $b): int => $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i']);

        return array_map(static fn ($e): array => $e['s'], \array_slice($scored, 0, self::MAX_SECTIONS));
    }

    private function system(string $context, string $lang): string
    {
        $rules = $lang === 'en'
            ? "You are the Draggo documentation assistant inside the Draggo page-builder for Contao 5. Answer the user's question ONLY using the documentation below. If the answer is not in the documentation, say you don't know and point to the closest guide/section — never invent features, fields or settings. Be concise and practical (steps, where to click). Answer in English. End with a line: Quellen: <section titles you used>."
            : "Du bist der Doku-Assistent von Draggo (visueller Page-Builder für Contao 5). Beantworte die Frage AUSSCHLIESSLICH mit der unten stehenden Dokumentation. Steht die Antwort nicht in der Doku, sage das ehrlich und verweise auf den nächstpassenden Guide/Abschnitt — erfinde KEINE Funktionen, Felder oder Einstellungen. Antworte knapp und praxisnah (Schritte, wo klicken). Antworte auf Deutsch. Schließe mit einer Zeile: Quellen: <verwendete Abschnittstitel>.";

        return $rules . "\n\n=== DRAGGO-DOKUMENTATION ===\n\n" . ($context !== '' ? $context : '(keine passenden Abschnitte gefunden)');
    }
}
