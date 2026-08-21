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

namespace Vtinnovations\Draggo\Form;

use Doctrine\DBAL\Connection;

/**
 * Visual form builder on top of Contao's native form system. Creates/edits real
 * tl_form + tl_form_field rows so submission handling, validation, e-mail
 * notification, spam protection and storage all stay Contao's job — Draggo only
 * provides the Elementor-style build + styling. The frontend renders the form
 * via Contao's FormGenerator (Controller::getForm), unchanged.
 *
 * All writes are parameterised; every field-type / column is whitelisted.
 */
final class FormBuilder
{
    private const SORT_STEP = 128;

    /** Allowed responsive width tokens (percent of row). Fixed vocabulary → no injection. */
    private const WIDTHS = ['100', '75', '66', '50', '33', '25'];

    /** Matches any draggo width class we own (desktop / tablet -t- / mobile -m-). */
    private const FW_RE = '/^draggo-fw-(?:t-|m-)?(?:100|75|66|50|33|25)$/';

    /**
     * Editor field "kinds" → the real tl_form_field column set. Keeps the UI
     * simple (e.g. "E-Mail" = a text field with the email validation) while only
     * ever writing native Contao field types.
     */
    private const KINDS = [
        'text'     => ['type' => 'text'],
        'email'    => ['type' => 'text', 'rgxp' => 'email'],
        'phone'    => ['type' => 'text', 'rgxp' => 'phone'],
        'number'   => ['type' => 'text', 'rgxp' => 'digit'],
        'url'      => ['type' => 'text', 'rgxp' => 'url'],
        'date'     => ['type' => 'text', 'rgxp' => 'date'],
        'textarea' => ['type' => 'textarea'],
        'select'   => ['type' => 'select'],
        'radio'    => ['type' => 'radio'],
        'checkbox' => ['type' => 'checkbox'],
        'upload'   => ['type' => 'upload'],
        'hidden'   => ['type' => 'hidden'],
        'html'     => ['type' => 'html'],
        'explanation' => ['type' => 'explanation'],
        'captcha'  => ['type' => 'captcha'],
        'submit'   => ['type' => 'submit'],
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    /** @return list<array{id:int,title:string}> */
    public function forms(): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT id, title FROM tl_form ORDER BY title');

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'title' => (string) $r['title']], $rows);
    }

    /** Create a new tl_form and return its id. */
    public function createForm(string $title, int $tstamp): int
    {
        $title = trim($title) !== '' ? mb_substr(trim($title), 0, 255) : 'Formular';
        $alias = $this->uniqueAlias($title);
        // sendViaEmail / storeValues are tinyint (int-backed) → never '' (strict
        // SQL). Omit them so the column defaults (0) apply; the user toggles them
        // on later via updateFormMeta.
        $this->db->insert('tl_form', [
            'tstamp'      => $tstamp,
            'title'       => $title,
            'alias'       => $alias,
            'method'      => 'POST',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function formExists(int $formId): bool
    {
        return (bool) $this->db->fetchOne('SELECT id FROM tl_form WHERE id = :id', ['id' => $formId]);
    }

    /** @return array<string,mixed> */
    public function formMeta(int $formId): array
    {
        $r = $this->db->fetchAssociative('SELECT id, title, sendViaEmail, recipient, subject, confirmation, storeValues FROM tl_form WHERE id = :id', ['id' => $formId]);
        if ($r === false) {
            return [];
        }

        return [
            'id' => (int) $r['id'],
            'title' => (string) $r['title'],
            'sendViaEmail' => (int) $r['sendViaEmail'] === 1,
            'recipient' => (string) $r['recipient'],
            'subject' => (string) $r['subject'],
            'confirmation' => (string) $r['confirmation'],
            'storeValues' => (int) $r['storeValues'] === 1,
        ];
    }

    /** @param array<string,mixed> $v */
    public function updateFormMeta(int $formId, array $v, int $tstamp): void
    {
        $set = ['tstamp' => $tstamp];
        if (\array_key_exists('title', $v)) {
            $set['title'] = mb_substr(trim((string) $v['title']), 0, 255) ?: 'Formular';
        }
        if (\array_key_exists('recipient', $v)) {
            // Keep only valid-looking comma-separated e-mails.
            $mails = array_filter(array_map('trim', explode(',', (string) $v['recipient'])), static fn (string $m): bool => $m !== '' && filter_var($m, FILTER_VALIDATE_EMAIL) !== false);
            $set['recipient'] = implode(',', $mails);
        }
        if (\array_key_exists('subject', $v)) {
            $set['subject'] = mb_substr(trim((string) $v['subject']), 0, 255);
        }
        if (\array_key_exists('confirmation', $v)) {
            $set['confirmation'] = trim((string) $v['confirmation']);
        }
        // tinyint columns → store 1/0 ints, never '' (strict SQL).
        if (\array_key_exists('sendViaEmail', $v)) {
            $set['sendViaEmail'] = !empty($v['sendViaEmail']) ? 1 : 0;
        }
        if (\array_key_exists('storeValues', $v)) {
            $set['storeValues'] = !empty($v['storeValues']) ? 1 : 0;
        }
        $this->db->update('tl_form', $set, ['id' => $formId]);
    }

    /** @return list<array<string,mixed>> */
    public function fields(int $formId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, type, label, name, mandatory, placeholder, rgxp, options, text, slabel, class, help, minlength, maxlength, autocomplete, value FROM tl_form_field WHERE pid = :pid ORDER BY sorting',
            ['pid' => $formId],
        );
        // Map the NON-text base types (textarea/select/…) 1:1. The four text
        // kinds (text/email/phone/number) all share type 'text' and are
        // disambiguated by rgxp below, so 'text' must NOT be derived from this
        // (lossy) map — handle it explicitly.
        $kindByType = [];
        foreach (self::KINDS as $kind => $def) {
            if ($def['type'] !== 'text') {
                $kindByType[$def['type']] = $kind;
            }
        }

        return array_map(function (array $r) use ($kindByType): array {
            $type = (string) $r['type'];
            $rgxp = (string) $r['rgxp'];
            $kind = match (true) {
                $type === 'text' && $rgxp === 'email' => 'email',
                $type === 'text' && $rgxp === 'phone' => 'phone',
                $type === 'text' && $rgxp === 'digit' => 'number',
                $type === 'text' && $rgxp === 'url'   => 'url',
                $type === 'text' && $rgxp === 'date'  => 'date',
                $type === 'text'                      => 'text',
                default                               => $kindByType[$type] ?? $type,
            };

            return [
                'id'        => (int) $r['id'],
                'kind'      => $kind,
                'type'      => $type,
                'label'     => (string) $r['label'],
                'name'      => (string) $r['name'],
                'mandatory' => (int) $r['mandatory'] === 1,
                'placeholder' => (string) $r['placeholder'],
                'options'   => $this->decodeOptions($r['options']),
                'text'      => (string) $r['text'],
                'slabel'    => (string) $r['slabel'],
                // Validation / a11y / convenience (native tl_form_field columns).
                'help'         => (string) ($r['help'] ?? ''),
                'minlength'    => (int) ($r['minlength'] ?? 0),
                'maxlength'    => (int) ($r['maxlength'] ?? 0),
                'autocomplete' => (string) ($r['autocomplete'] ?? ''),
                'value'        => (string) ($r['value'] ?? ''),
                // Responsive per-field width {d,t,m} parsed out of the native
                // tl_form_field.class column (draggo-fw-* tokens).
                'width'     => $this->parseWidthClasses((string) $r['class']),
            ];
        }, $rows);
    }

    /** Add a field of the given editor kind; returns the new field id. */
    public function addField(int $formId, string $kind, int $tstamp): int
    {
        $def = self::KINDS[$kind] ?? self::KINDS['text'];
        $max = (int) $this->db->fetchOne('SELECT MAX(sorting) FROM tl_form_field WHERE pid = :pid', ['pid' => $formId]);
        $insert = [
            'pid'     => $formId,
            'sorting' => $max + self::SORT_STEP,
            'tstamp'  => $tstamp,
            'type'    => $def['type'],
            'rgxp'    => $def['rgxp'] ?? '',
        ];
        if ($def['type'] === 'submit') {
            $insert['slabel'] = 'Absenden';
        } elseif (!\in_array($def['type'], ['html', 'explanation', 'captcha', 'hidden'], true)) {
            $insert['label'] = ucfirst($kind);
            $insert['name'] = $this->uniqueName($formId, $kind);
        }

        $this->db->insert('tl_form_field', $insert);

        return (int) $this->db->lastInsertId();
    }

    /** Update a whitelisted set of properties on one field. @param array<string,mixed> $v */
    public function updateField(int $fieldId, array $v, int $tstamp): void
    {
        $cur = $this->db->fetchAssociative('SELECT pid, type, name, class FROM tl_form_field WHERE id = :id', ['id' => $fieldId]);
        if ($cur === false) {
            return;
        }
        $set = ['tstamp' => $tstamp];
        if (\array_key_exists('label', $v)) {
            $set['label'] = mb_substr(trim((string) $v['label']), 0, 255);
        }
        if (\array_key_exists('mandatory', $v)) {
            $set['mandatory'] = !empty($v['mandatory']) ? 1 : 0; // tinyint
        }
        if (\array_key_exists('placeholder', $v)) {
            $set['placeholder'] = mb_substr(trim((string) $v['placeholder']), 0, 255);
        }
        if (\array_key_exists('text', $v)) {
            $set['text'] = trim((string) $v['text']);
        }
        if (\array_key_exists('slabel', $v) && (string) $cur['type'] === 'submit') {
            $set['slabel'] = mb_substr(trim((string) $v['slabel']), 0, 255) ?: 'Absenden';
        }
        if (\array_key_exists('options', $v) && \is_array($v['options'])) {
            $set['options'] = serialize($this->cleanOptions($v['options']));
        }
        // Renaming: keep a valid, unique submission key.
        if (\array_key_exists('name', $v) && (string) $cur['name'] !== '') {
            $name = $this->slug((string) $v['name']);
            if ($name !== '') {
                $set['name'] = $this->uniqueName((int) $cur['pid'], $name, $fieldId);
            }
        }
        // Validation / a11y / convenience.
        if (\array_key_exists('help', $v)) {
            $set['help'] = mb_substr(trim((string) $v['help']), 0, 255);
        }
        foreach (['minlength', 'maxlength'] as $lk) {
            if (\array_key_exists($lk, $v)) {
                $n = (int) $v[$lk];
                $set[$lk] = $n > 0 ? $n : 0; // 0 = no limit
            }
        }
        if (\array_key_exists('autocomplete', $v)) {
            // HTML autocomplete tokens: letters, digits, spaces, hyphens only.
            $ac = strtolower(trim((string) $v['autocomplete']));
            $set['autocomplete'] = mb_substr(preg_replace('/[^a-z0-9 -]/', '', $ac) ?? '', 0, 64);
        }
        if (\array_key_exists('value', $v)) {
            $set['value'] = mb_substr(trim((string) $v['value']), 0, 255);
        }
        // Responsive width → rewrite draggo-fw-* tokens in the native `class`
        // column, preserving any user classes (width tokens appended last so
        // the 255-char clamp can never truncate a token).
        if (\array_key_exists('width', $v) && \is_array($v['width'])) {
            $set['class'] = $this->applyWidthClasses((string) ($cur['class'] ?? ''), $v['width']);
        }
        $this->db->update('tl_form_field', $set, ['id' => $fieldId]);
    }

    public function deleteField(int $fieldId): void
    {
        $this->db->delete('tl_form_field', ['id' => $fieldId]);
    }

    /**
     * Clone a field (all settings) and place the copy right after the source.
     * The submission key gets a unique "_copy" suffix so it never collides.
     * Returns the new field id, or 0 when the source doesn't exist.
     */
    public function duplicateField(int $fieldId, int $tstamp): int
    {
        $row = $this->db->fetchAssociative('SELECT * FROM tl_form_field WHERE id = :id', ['id' => $fieldId]);
        if ($row === false) {
            return 0;
        }
        $pid = (int) $row['pid'];
        unset($row['id']);
        $row['tstamp'] = $tstamp;
        $row['sorting'] = (int) $row['sorting'] + 1; // directly after the original
        if (!empty($row['name'])) {
            $row['name'] = $this->uniqueName($pid, $this->slug((string) $row['name'] . '_copy'));
        }
        $this->db->insert('tl_form_field', $row);

        return (int) $this->db->lastInsertId();
    }

    /** @param list<int> $orderedIds */
    public function reorder(int $formId, array $orderedIds, int $tstamp): void
    {
        $this->db->transactional(function (Connection $c) use ($formId, $orderedIds, $tstamp): void {
            $s = self::SORT_STEP;
            foreach ($orderedIds as $id) {
                $c->update('tl_form_field', ['sorting' => $s, 'tstamp' => $tstamp], ['id' => (int) $id, 'pid' => $formId]);
                $s += self::SORT_STEP;
            }
        });
    }

    /** @return list<string> editor kind keys (for the palette) */
    public static function kinds(): array
    {
        return array_keys(self::KINDS);
    }

    // ── helpers ──────────────────────────────────────────────────────
    private function decodeOptions(mixed $raw): array
    {
        $arr = @unserialize((string) $raw, ['allowed_classes' => false]);
        if (!\is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $o) {
            if (\is_array($o)) {
                $out[] = ['value' => (string) ($o['value'] ?? ''), 'label' => (string) ($o['label'] ?? '')];
            }
        }

        return $out;
    }

    private function cleanOptions(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (!\is_array($r)) {
                continue;
            }
            $label = trim((string) ($r['label'] ?? $r['value'] ?? ''));
            if ($label === '') {
                continue;
            }
            $value = trim((string) ($r['value'] ?? '')) ?: $label;
            $out[] = ['value' => $value, 'label' => $label, 'default' => '', 'group' => ''];
        }

        return $out;
    }

    private function uniqueName(int $formId, string $base, int $exceptId = 0): string
    {
        $base = $this->slug($base) ?: 'field';
        $name = $base;
        $i = 1;
        while (true) {
            $clash = $this->db->fetchOne(
                'SELECT id FROM tl_form_field WHERE pid = :pid AND name = :name' . ($exceptId ? ' AND id != :ex' : ''),
                array_merge(['pid' => $formId, 'name' => $name], $exceptId ? ['ex' => $exceptId] : []),
            );
            if (!$clash) {
                return $name;
            }
            $name = $base . '_' . $i++;
        }
    }

    private function uniqueAlias(string $title): string
    {
        $base = $this->slug($title) ?: 'form';
        $alias = $base;
        $i = 1;
        while ($this->db->fetchOne('SELECT id FROM tl_form WHERE alias = :a', ['a' => $alias])) {
            $alias = $base . '-' . $i++;
        }

        return $alias;
    }

    /**
     * Rebuild the `class` string: drop every draggo-fw-* token we manage, keep
     * all other (user) classes verbatim and in order, then append the new width
     * tokens LAST. The user portion is clamped first so the 255-char column cap
     * never truncates a width token mid-string.
     *
     * @param array{d?:string,t?:string,m?:string} $w
     */
    private function applyWidthClasses(string $existing, array $w): string
    {
        $kept = array_values(array_filter(
            preg_split('/\s+/', trim($existing)) ?: [],
            fn (string $c): bool => $c !== '' && !preg_match(self::FW_RE, $c),
        ));

        $tokens = [];
        foreach (['d' => 'draggo-fw-', 't' => 'draggo-fw-t-', 'm' => 'draggo-fw-m-'] as $k => $prefix) {
            $val = (string) ($w[$k] ?? '');
            if (\in_array($val, self::WIDTHS, true)) {
                $tokens[] = $prefix . $val;
            }
        }
        $tokenStr = implode(' ', $tokens);

        $userStr = implode(' ', $kept);
        $budget = 255 - ($tokenStr === '' ? 0 : \strlen($tokenStr) + 1);
        if (\strlen($userStr) > $budget) {
            $userStr = rtrim(substr($userStr, 0, max(0, $budget)));
        }

        return trim($userStr . ' ' . $tokenStr);
    }

    /**
     * Parse {d,t,m} width values back out of a class string.
     *
     * @return array{d:string,t:string,m:string}
     */
    private function parseWidthClasses(string $class): array
    {
        $w = ['d' => '', 't' => '', 'm' => ''];
        foreach (preg_split('/\s+/', trim($class)) ?: [] as $c) {
            if (preg_match('/^draggo-fw-m-(100|75|66|50|33|25)$/', $c, $mm)) {
                $w['m'] = $mm[1];
            } elseif (preg_match('/^draggo-fw-t-(100|75|66|50|33|25)$/', $c, $mm)) {
                $w['t'] = $mm[1];
            } elseif (preg_match('/^draggo-fw-(100|75|66|50|33|25)$/', $c, $mm)) {
                $w['d'] = $mm[1];
            }
        }

        return $w;
    }

    private function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';

        return trim($s, '_');
    }
}
