<?php

declare(strict_types=1);

function is_gift_box_product(array $product): bool
{
    return !empty($product['is_gift_box']) || ($product['category_slug'] ?? '') === 'coffrets';
}

function gift_box_options(string $options): array
{
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $options)), fn($option) => $option !== '')));
}

function gift_box_components(PDO $pdo, int $giftBoxId): array
{
    $statement = $pdo->prepare(
        'SELECT id component_id,component_name name,quantity,sizes,colors
         FROM gift_box_text_components WHERE gift_box_id=? ORDER BY id'
    );
    $statement->execute([$giftBoxId]);
    $components = $statement->fetchAll();
    foreach ($components as &$component) {
        $component['size_options'] = gift_box_options((string) $component['sizes']);
        $component['color_options'] = gift_box_options((string) $component['colors']);
    }
    unset($component);
    return $components;
}

function resolve_gift_box_selection(PDO $pdo, int $giftBoxId, mixed $submitted): array
{
    if (!is_array($submitted)) throw new RuntimeException('Vérifiez les choix des composants du coffret.');
    $definitions = gift_box_components($pdo, $giftBoxId);
    if (!$definitions) throw new RuntimeException('La composition de ce coffret n’a pas encore été configurée.');

    $components = [];
    foreach ($definitions as $definition) {
        $componentId = (int) $definition['component_id'];
        $selection = $submitted[$componentId] ?? [];
        if (!is_array($selection)) $selection = [];
        $size = resolve_gift_box_option($selection['size'] ?? '', $definition['size_options'], 'taille', (string) $definition['name']);
        $color = resolve_gift_box_option($selection['color'] ?? '', $definition['color_options'], 'couleur', (string) $definition['name']);
        $components[] = [
            'component_id' => $componentId,
            'product_name' => (string) $definition['name'],
            'size' => $size,
            'color' => $color,
            'quantity_per_bundle' => (int) $definition['quantity'],
        ];
    }
    return $components;
}

function resolve_gift_box_option(mixed $submitted, array $allowed, string $label, string $componentName): string
{
    if (!$allowed) return '';
    if (count($allowed) === 1) return $allowed[0];
    if (!is_string($submitted) || !in_array($submitted, $allowed, true)) {
        throw new RuntimeException('Choisissez ' . $label . ' pour « ' . $componentName . ' ».');
    }
    return $submitted;
}

function cart_item_variant_id(string|int $key, array $item): int
{
    return (int) ($item['variant_id'] ?? (is_numeric($key) ? $key : 0));
}

function cart_line_key(int $variantId, array $components = []): string
{
    if (!$components) return (string) $variantId;
    usort($components, fn($a, $b) => (int) $a['component_id'] <=> (int) $b['component_id']);
    $selection = array_map(fn($component) => implode(':', [
        (int) $component['component_id'],
        (string) $component['size'],
        (string) $component['color'],
    ]), $components);
    return 'b' . $variantId . '-' . substr(hash('sha256', implode('|', $selection)), 0, 12);
}

function cart_line_available_stock(PDO $pdo, array $item): int
{
    $statement = $pdo->prepare('SELECT stock FROM variants WHERE id=?');
    $statement->execute([cart_item_variant_id((string) ($item['variant_id'] ?? ''), $item)]);
    return max(0, (int) $statement->fetchColumn());
}
