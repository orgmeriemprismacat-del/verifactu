#!/usr/bin/env php
<?php

declare(strict_types=1);

main($argv);

function main(array $argv): void
{
    try {
        $options = parseArgs($argv);

        if ($options['help']) {
            printHelp();
            exit(0);
        }

        if ($options['input'] === null) {
            throw new RuntimeException('Missing required --input=FILE');
        }

        $aliases = defaultBoardAliases();
        foreach ($options['aliases'] as $alias => $target) {
            $aliases[normalizeKey($alias)] = $target;
        }

        $cards = loadCards((string) $options['input'], $options, $aliases);
        if ($options['limit'] !== null) {
            $cards = array_slice($cards, 0, (int) $options['limit']);
        }

        if ($cards === []) {
            throw new RuntimeException('No cards found in input file');
        }

        if ($options['offline']) {
            printOfflinePlan($cards);
            exit(0);
        }

        $key = (string) ($options['key'] ?? getenv('TRELLO_KEY') ?: '');
        $token = (string) ($options['token'] ?? getenv('TRELLO_TOKEN') ?: '');

        if ($key === '' || $token === '') {
            throw new RuntimeException('Missing Trello credentials. Set TRELLO_KEY and TRELLO_TOKEN, or pass --key=... --token=...');
        }

        if ($options['execute'] && $options['dry_run']) {
            throw new RuntimeException('Internal option conflict: --execute and dry-run are both active');
        }

        $api = new TrelloApi($key, $token, (int) $options['timeout']);
        $importer = new TrelloImporter($api, $options);
        $summary = $importer->import($cards);

        echo PHP_EOL;
        echo 'Summary: ', json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
        exit($summary['errors'] > 0 ? 1 : 0);
    } catch (Throwable $exception) {
        fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

function parseArgs(array $argv): array
{
    $options = [
        'input' => null,
        'key' => null,
        'token' => null,
        'execute' => false,
        'dry_run' => true,
        'offline' => false,
        'create_lists' => false,
        'create_labels' => false,
        'ignore_missing_labels' => false,
        'allow_duplicates' => false,
        'default_board' => null,
        'default_list' => null,
        'default_label_color' => 'blue',
        'position' => 'bottom',
        'limit' => null,
        'timeout' => 30,
        'aliases' => [],
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }

        if ($arg === '--execute') {
            $options['execute'] = true;
            $options['dry_run'] = false;
            continue;
        }

        if ($arg === '--dry-run') {
            $options['execute'] = false;
            $options['dry_run'] = true;
            continue;
        }

        if ($arg === '--offline') {
            $options['offline'] = true;
            continue;
        }

        if ($arg === '--create-lists') {
            $options['create_lists'] = true;
            continue;
        }

        if ($arg === '--create-labels') {
            $options['create_labels'] = true;
            continue;
        }

        if ($arg === '--ignore-missing-labels') {
            $options['ignore_missing_labels'] = true;
            continue;
        }

        if ($arg === '--allow-duplicates') {
            $options['allow_duplicates'] = true;
            continue;
        }

        if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
            throw new RuntimeException('Unknown option: ' . $arg);
        }

        [$name, $value] = explode('=', substr($arg, 2), 2);
        switch ($name) {
            case 'input':
                $options['input'] = $value;
                break;
            case 'key':
                $options['key'] = $value;
                break;
            case 'token':
                $options['token'] = $value;
                break;
            case 'board':
                $options['default_board'] = $value;
                break;
            case 'list':
                $options['default_list'] = $value;
                break;
            case 'position':
                $options['position'] = $value;
                break;
            case 'default-label-color':
                $options['default_label_color'] = $value;
                break;
            case 'limit':
                $options['limit'] = max(0, (int) $value);
                break;
            case 'timeout':
                $options['timeout'] = max(1, (int) $value);
                break;
            case 'alias':
                if (!str_contains($value, '=')) {
                    throw new RuntimeException('Invalid --alias value. Use --alias=SHORT=Full board name');
                }

                [$alias, $target] = explode('=', $value, 2);
                $options['aliases'][$alias] = $target;
                break;
            default:
                throw new RuntimeException('Unknown option: --' . $name);
        }
    }

    if ($options['execute'] && $options['offline']) {
        throw new RuntimeException('--offline cannot be used with --execute');
    }

    return $options;
}

function printHelp(): void
{
    echo <<<'TXT'
Usage:
  php trello-import-cards.php --input=cards.json
  php trello-import-cards.php --input=auditoria.md --offline
  php trello-import-cards.php --input=cards.json --execute --create-lists --create-labels

Credentials:
  Set TRELLO_KEY and TRELLO_TOKEN in the environment, or pass --key=... --token=...

Safe defaults:
  Without --execute the script only performs a dry-run.
  Use --offline to parse the input without calling Trello.

Input:
  JSON with {"defaults": {...}, "cards": [...]} or a Markdown audit table with:
  | Trello | Llista | Targeta proposada | Etiquetes |

Useful options:
  --board=NAME                  Default board for cards without board
  --list=NAME                   Default list for cards without list
  --create-lists                Create missing Trello lists
  --create-labels               Create missing Trello labels
  --ignore-missing-labels       Import card even if a label is missing
  --allow-duplicates            Do not skip existing card names in same list
  --limit=N                     Process only first N cards
  --alias=SHORT=Full board name Add or override board alias

TXT;
}

function loadCards(string $path, array $options, array $aliases): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Input file not found: ' . $path);
    }

    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    if ($extension === 'json') {
        return loadJsonCards($path, $options, $aliases);
    }

    if ($extension === 'md' || $extension === 'markdown') {
        return loadMarkdownCards($path, $options, $aliases);
    }

    throw new RuntimeException('Unsupported input extension: .' . $extension);
}

function loadJsonCards(string $path, array $options, array $aliases): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Could not read input file: ' . $path);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
    }

    $defaults = is_array($data['defaults'] ?? null) ? $data['defaults'] : [];
    $rows = is_array($data['cards'] ?? null) ? $data['cards'] : $data;

    if (!is_array($rows)) {
        throw new RuntimeException('JSON input must contain a cards array');
    }

    $cards = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            throw new RuntimeException('Invalid card at index ' . $index);
        }

        $card = array_replace($defaults, $row);
        $cards[] = normalizeCard($card, $options, $aliases, $path);
    }

    return $cards;
}

function loadMarkdownCards(string $path, array $options, array $aliases): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Could not read input file: ' . $path);
    }

    $cards = [];
    $header = null;
    foreach ($lines as $lineNumber => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || !str_starts_with($trimmed, '|')) {
            $header = null;
            continue;
        }

        $cells = splitMarkdownRow($trimmed);
        if (count($cells) < 4) {
            continue;
        }

        $normalized = array_map(static fn (string $cell): string => normalizeKey(stripInlineMarkdown($cell)), $cells);
        if (in_array('trello', $normalized, true) && in_array('llista', $normalized, true)
            && in_array('targeta proposada', $normalized, true) && in_array('etiquetes', $normalized, true)) {
            $header = [
                'board' => array_search('trello', $normalized, true),
                'list' => array_search('llista', $normalized, true),
                'name' => array_search('targeta proposada', $normalized, true),
                'labels' => array_search('etiquetes', $normalized, true),
            ];
            continue;
        }

        if ($header === null || isMarkdownSeparator($cells)) {
            continue;
        }

        $board = $cells[$header['board']] ?? '';
        $list = $cells[$header['list']] ?? '';
        $name = $cells[$header['name']] ?? '';
        $labels = $cells[$header['labels']] ?? '';

        if (trim($board) === '' || trim($list) === '' || trim($name) === '') {
            continue;
        }

        $cards[] = normalizeCard([
            'board' => stripInlineMarkdown($board),
            'list' => stripInlineMarkdown($list),
            'name' => stripInlineMarkdown($name),
            'desc' => markdownCardDescription($path, $lineNumber + 1, $board, $list, $name, $labels),
            'labels' => parseLabels($labels),
        ], $options, $aliases, $path);
    }

    return $cards;
}

function normalizeCard(array $card, array $options, array $aliases, string $sourcePath): array
{
    $board = trim((string) ($card['board'] ?? $card['trello'] ?? $options['default_board'] ?? ''));
    $list = trim((string) ($card['list'] ?? $card['llista'] ?? $options['default_list'] ?? ''));
    $name = trim((string) ($card['name'] ?? $card['targeta'] ?? $card['title'] ?? ''));

    if ($board === '') {
        throw new RuntimeException('Card without board in ' . $sourcePath);
    }

    if ($list === '') {
        throw new RuntimeException('Card without list in ' . $sourcePath);
    }

    if ($name === '') {
        throw new RuntimeException('Card without name in ' . $sourcePath);
    }

    $labels = $card['labels'] ?? $card['etiquetes'] ?? [];
    if (is_string($labels)) {
        $labels = parseLabels($labels);
    }

    if (!is_array($labels)) {
        throw new RuntimeException('Invalid labels for card: ' . $name);
    }

    $checklists = $card['checklists'] ?? [];
    if (!is_array($checklists)) {
        throw new RuntimeException('Invalid checklists for card: ' . $name);
    }

    return [
        'board' => resolveBoardAlias($board, $aliases),
        'list' => $list,
        'name' => $name,
        'desc' => (string) ($card['desc'] ?? $card['description'] ?? ''),
        'labels' => normalizeLabelItems($labels),
        'pos' => (string) ($card['pos'] ?? $card['position'] ?? $options['position']),
        'due' => isset($card['due']) ? (string) $card['due'] : null,
        'urlSource' => isset($card['urlSource']) ? (string) $card['urlSource'] : null,
        'checklists' => $checklists,
        'source' => $sourcePath,
    ];
}

function normalizeLabelItems(array $labels): array
{
    $result = [];
    foreach ($labels as $label) {
        if (is_string($label)) {
            $name = trim($label);
            if ($name !== '') {
                $result[] = ['name' => $name, 'color' => null];
            }
            continue;
        }

        if (is_array($label)) {
            $name = trim((string) ($label['name'] ?? ''));
            if ($name !== '') {
                $result[] = ['name' => $name, 'color' => isset($label['color']) ? (string) $label['color'] : null];
            }
        }
    }

    return $result;
}

function parseLabels(string $labels): array
{
    $plain = stripInlineMarkdown($labels);
    $parts = array_map('trim', explode(',', $plain));

    return array_values(array_filter($parts, static fn (string $label): bool => $label !== ''));
}

function markdownCardDescription(
    string $path,
    int $lineNumber,
    string $board,
    string $list,
    string $name,
    string $labels
): string {
    return implode(PHP_EOL, [
        'Targeta creada des de auditoria local.',
        '',
        'Font: ' . basename($path) . ':' . $lineNumber,
        'Trello proposat: ' . stripInlineMarkdown($board),
        'Llista proposada: ' . stripInlineMarkdown($list),
        'Etiquetes proposades: ' . stripInlineMarkdown($labels),
        '',
        'Proposta original:',
        stripInlineMarkdown($name),
    ]);
}

function splitMarkdownRow(string $line): array
{
    $line = trim($line);
    if (str_starts_with($line, '|')) {
        $line = substr($line, 1);
    }
    if (str_ends_with($line, '|')) {
        $line = substr($line, 0, -1);
    }

    $cells = [];
    $buffer = '';
    $escaped = false;
    $length = strlen($line);

    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];
        if ($escaped) {
            $buffer .= $char;
            $escaped = false;
            continue;
        }

        if ($char === '\\') {
            $escaped = true;
            continue;
        }

        if ($char === '|') {
            $cells[] = trim($buffer);
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $cells[] = trim($buffer);

    return $cells;
}

function isMarkdownSeparator(array $cells): bool
{
    foreach ($cells as $cell) {
        if (preg_match('/^\s*:?-{3,}:?\s*$/', $cell) !== 1) {
            return false;
        }
    }

    return true;
}

function stripInlineMarkdown(string $text): string
{
    $text = str_replace(['\\|', '**', '__'], ['|', '', ''], $text);
    $text = preg_replace('/`([^`]*)`/', '$1', $text) ?? $text;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function defaultBoardAliases(): array
{
    $aliases = [
        'fitxes funcionals i documentacio de casos' => 'VeriFactu / SIF · Fitxes funcionals i documentació de casos',
        'fitxes funcionals i documentació de casos' => 'VeriFactu / SIF · Fitxes funcionals i documentació de casos',
        'proves/entorns/produccio' => 'VeriFactu / SIF · Proves, entorns i producció',
        'proves/entorns/producció' => 'VeriFactu / SIF · Proves, entorns i producció',
        'proves, entorns i produccio' => 'VeriFactu / SIF · Proves, entorns i producció',
        'proves, entorns i producció' => 'VeriFactu / SIF · Proves, entorns i producció',
        'intranet/interficies' => '4 VeriFactu / SIF · Intranet, interfície i notificacions',
        'intranet/interfícies' => '4 VeriFactu / SIF · Intranet, interfície i notificacions',
        'intranet/interficie/notificacions' => '4 VeriFactu / SIF · Intranet, interfície i notificacions',
        'sif pay.prisma.cat' => 'VeriFactu / SIF · SIF pay.prisma.cat',
        'control del projecte' => 'VeriFactu / SIF · Control del projecte',
        'casos d\'us / analisi funcional' => 'VeriFactu / SIF · Casos d’ús / Anàlisi funcional',
        'casos d\'ús / anàlisi funcional' => 'VeriFactu / SIF · Casos d’ús / Anàlisi funcional',
    ];

    $normalized = [];
    foreach ($aliases as $alias => $target) {
        $normalized[normalizeKey($alias)] = $target;
    }

    return $normalized;
}

function resolveBoardAlias(string $board, array $aliases): string
{
    return $aliases[normalizeKey($board)] ?? $board;
}

function normalizeKey(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function printOfflinePlan(array $cards): void
{
    echo 'Offline plan: ', count($cards), ' cards', PHP_EOL;
    foreach ($cards as $index => $card) {
        echo '[' . ($index + 1) . '] ',
            $card['board'], ' > ', $card['list'], ' > ', $card['name'],
            labelSuffix($card['labels']),
            PHP_EOL;
    }
}

function labelSuffix(array $labels): string
{
    if ($labels === []) {
        return '';
    }

    return ' [' . implode(', ', array_map(static fn (array $label): string => $label['name'], $labels)) . ']';
}

final class TrelloImporter
{
    private array $boards = [];
    private array $listsByBoard = [];
    private array $labelsByBoard = [];
    private array $cardsByList = [];

    public function __construct(
        private TrelloApi $api,
        private array $options
    ) {
    }

    public function import(array $cards): array
    {
        $summary = [
            'planned' => count($cards),
            'would_create' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
            'dry_run' => $this->options['dry_run'],
        ];

        foreach ($cards as $card) {
            try {
                $result = $this->importCard($card);
                $summary[$result]++;
            } catch (Throwable $exception) {
                $summary['errors']++;
                fwrite(STDERR, '[ERROR] ' . $card['board'] . ' > ' . $card['list'] . ' > ' . $card['name'] . ': ' . $exception->getMessage() . PHP_EOL);
            }
        }

        return $summary;
    }

    private function importCard(array $card): string
    {
        $board = $this->resolveBoard((string) $card['board']);
        $list = $this->resolveList($board, (string) $card['list']);
        $labelIds = $this->resolveLabels($board, $card['labels']);

        if ($list['id'] !== null && !$this->options['allow_duplicates'] && $this->cardExists((string) $list['id'], (string) $card['name'])) {
            echo '[SKIP] duplicate: ', $card['board'], ' > ', $card['list'], ' > ', $card['name'], PHP_EOL;
            return 'skipped';
        }

        if ($this->options['dry_run']) {
            echo '[DRY] create card: ', $card['board'], ' > ', $card['list'], ' > ', $card['name'], labelSuffix($card['labels']), PHP_EOL;
            if ($list['id'] === null) {
                echo '      would create list: ', $card['list'], PHP_EOL;
            }
            foreach ($labelIds['missing'] as $label) {
                echo '      would create label: ', $label, PHP_EOL;
            }
            return 'would_create';
        }

        if ($list['id'] === null) {
            throw new RuntimeException('List was not resolved');
        }

        $createdCard = $this->api->post('/cards', array_filter([
            'idList' => $list['id'],
            'name' => $card['name'],
            'desc' => $card['desc'],
            'pos' => $card['pos'],
            'due' => $card['due'],
            'urlSource' => $card['urlSource'],
            'idLabels' => $labelIds['ids'] === [] ? null : implode(',', $labelIds['ids']),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        $this->createChecklists((string) $createdCard['id'], $card['checklists']);
        $this->cardsByList[(string) $list['id']][normalizeKey((string) $card['name'])] = true;

        echo '[OK] created: ', $card['board'], ' > ', $card['list'], ' > ', $card['name'], PHP_EOL;

        return 'created';
    }

    private function resolveBoard(string $boardName): array
    {
        if ($this->boards === []) {
            $boards = $this->api->get('/members/me/boards', [
                'fields' => 'name,shortLink,url,closed',
                'filter' => 'open',
            ]);

            foreach ($boards as $board) {
                $this->boards[(string) $board['id']] = $board;
                $this->boards[normalizeKey((string) $board['name'])] = $board;
                $this->boards[normalizeKey((string) ($board['shortLink'] ?? ''))] = $board;
            }
        }

        $key = normalizeKey($boardName);
        if (!isset($this->boards[$key])) {
            throw new RuntimeException('Board not found: ' . $boardName);
        }

        return $this->boards[$key];
    }

    private function resolveList(array $board, string $listName): array
    {
        $boardId = (string) $board['id'];
        if (!isset($this->listsByBoard[$boardId])) {
            $lists = $this->api->get('/boards/' . rawurlencode($boardId) . '/lists', [
                'fields' => 'name,closed',
                'filter' => 'open',
            ]);

            $this->listsByBoard[$boardId] = [];
            foreach ($lists as $list) {
                $this->listsByBoard[$boardId][normalizeKey((string) $list['name'])] = $list;
            }
        }

        $key = normalizeKey($listName);
        if (isset($this->listsByBoard[$boardId][$key])) {
            return $this->listsByBoard[$boardId][$key];
        }

        if (!$this->options['create_lists']) {
            throw new RuntimeException('List not found: ' . $listName . ' (use --create-lists to create it)');
        }

        if ($this->options['dry_run']) {
            return ['id' => null, 'name' => $listName];
        }

        $list = $this->api->post('/boards/' . rawurlencode($boardId) . '/lists', [
            'name' => $listName,
            'pos' => 'bottom',
        ]);
        $this->listsByBoard[$boardId][$key] = $list;

        echo '[OK] created list: ', $board['name'], ' > ', $listName, PHP_EOL;

        return $list;
    }

    private function resolveLabels(array $board, array $labels): array
    {
        $boardId = (string) $board['id'];
        if (!isset($this->labelsByBoard[$boardId])) {
            $boardLabels = $this->api->get('/boards/' . rawurlencode($boardId) . '/labels', [
                'fields' => 'name,color',
                'limit' => '1000',
            ]);

            $this->labelsByBoard[$boardId] = [];
            foreach ($boardLabels as $label) {
                $name = trim((string) ($label['name'] ?? ''));
                if ($name !== '') {
                    $this->labelsByBoard[$boardId][normalizeKey($name)] = $label;
                }
            }
        }

        $ids = [];
        $missing = [];
        foreach ($labels as $label) {
            $name = (string) $label['name'];
            $key = normalizeKey($name);
            if (isset($this->labelsByBoard[$boardId][$key])) {
                $ids[] = (string) $this->labelsByBoard[$boardId][$key]['id'];
                continue;
            }

            if ($this->options['create_labels']) {
                $missing[] = $name;
                if (!$this->options['dry_run']) {
                    $created = $this->api->post('/boards/' . rawurlencode($boardId) . '/labels', [
                        'name' => $name,
                        'color' => $label['color'] ?: $this->options['default_label_color'],
                    ]);
                    $this->labelsByBoard[$boardId][$key] = $created;
                    $ids[] = (string) $created['id'];
                    echo '[OK] created label: ', $board['name'], ' > ', $name, PHP_EOL;
                }
                continue;
            }

            if ($this->options['ignore_missing_labels']) {
                fwrite(STDERR, '[WARN] missing label ignored: ' . $board['name'] . ' > ' . $name . PHP_EOL);
                continue;
            }

            throw new RuntimeException('Label not found: ' . $name . ' (use --create-labels or --ignore-missing-labels)');
        }

        return ['ids' => array_values(array_unique($ids)), 'missing' => $missing];
    }

    private function cardExists(string $listId, string $name): bool
    {
        if (!isset($this->cardsByList[$listId])) {
            $cards = $this->api->get('/lists/' . rawurlencode($listId) . '/cards', [
                'fields' => 'name,closed',
                'filter' => 'open',
            ]);

            $this->cardsByList[$listId] = [];
            foreach ($cards as $card) {
                $this->cardsByList[$listId][normalizeKey((string) $card['name'])] = true;
            }
        }

        return isset($this->cardsByList[$listId][normalizeKey($name)]);
    }

    private function createChecklists(string $cardId, array $checklists): void
    {
        foreach ($checklists as $checklist) {
            if (!is_array($checklist)) {
                continue;
            }

            $name = trim((string) ($checklist['name'] ?? 'Checklist'));
            $items = $checklist['items'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            $created = $this->api->post('/cards/' . rawurlencode($cardId) . '/checklists', [
                'name' => $name,
            ]);

            foreach ($items as $item) {
                $itemName = is_array($item) ? (string) ($item['name'] ?? '') : (string) $item;
                $itemName = trim($itemName);
                if ($itemName === '') {
                    continue;
                }

                $this->api->post('/checklists/' . rawurlencode((string) $created['id']) . '/checkItems', [
                    'name' => $itemName,
                    'checked' => is_array($item) && !empty($item['checked']) ? 'true' : 'false',
                ]);
            }
        }
    }
}

final class TrelloApi
{
    private const BASE_URL = 'https://api.trello.com/1';

    public function __construct(
        private string $key,
        private string $token,
        private int $timeout
    ) {
    }

    public function get(string $path, array $params = []): array
    {
        return $this->request('GET', $path, $params);
    }

    public function post(string $path, array $params = []): array
    {
        return $this->request('POST', $path, $params);
    }

    private function request(string $method, string $path, array $params): array
    {
        $params = array_merge($params, [
            'key' => $this->key,
            'token' => $this->token,
        ]);

        $url = self::BASE_URL . $path;
        $body = null;
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
        } else {
            $body = http_build_query($params);
        }

        if (function_exists('curl_init')) {
            [$status, $response] = $this->requestWithCurl($method, $url, $body);
        } else {
            [$status, $response] = $this->requestWithStreams($method, $url, $body);
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Trello API HTTP ' . $status . ': ' . $response);
        }

        if (trim($response) === '') {
            return [];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid Trello JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    private function requestWithCurl(string $method, string $url, ?string $body): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Could not initialize curl');
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('Curl error: ' . $error);
        }

        curl_close($curl);

        return [$status, (string) $response];
    }

    private function requestWithStreams(string $method, string $url, ?string $body): array
    {
        $headers = "Content-Type: application/x-www-form-urlencoded\r\n";
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => $this->timeout,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('HTTP request failed');
        }

        $status = 0;
        $headersOut = $http_response_header ?? [];
        foreach ($headersOut as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }

        return [$status, $response];
    }
}
