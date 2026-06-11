<?php
function mb_proposal_letter_clean_style(string $style): string {
    $allowed = [
        'text-align','font-size','font-family','color','background-color',
        'margin','margin-left','margin-right','margin-top','margin-bottom',
        'padding','border','border-collapse','width','height'
    ];
    $clean = [];
    foreach (explode(';', $style) as $rule) {
        if (strpos($rule, ':') === false) continue;
        [$prop, $value] = array_map('trim', explode(':', $rule, 2));
        $prop = strtolower($prop);
        if (!in_array($prop, $allowed, true)) continue;
        if (preg_match('/expression\s*\(|javascript\s*:|url\s*\(/i', $value)) continue;
        $clean[] = $prop . ': ' . $value;
    }
    return implode('; ', $clean);
}

function mb_proposal_letter_sanitize_node(DOMNode $node, DOMDocument $doc, array $allowedTags, array $tableTags): void {
    if ($node->nodeType === XML_ELEMENT_NODE) {
        $tag = strtolower($node->nodeName);
        if (!in_array($tag, $allowedTags, true)) {
            $fragment = $doc->createDocumentFragment();
            while ($node->firstChild) {
                $fragment->appendChild($node->firstChild);
            }
            $node->parentNode->replaceChild($fragment, $node);
            return;
        }
        if ($node->hasAttributes()) {
            $remove = [];
            foreach ($node->attributes as $attr) {
                $name = strtolower($attr->name);
                if (strpos($name, 'on') === 0 || in_array($name, ['href','src'], true)) {
                    $remove[] = $attr->name;
                    continue;
                }
                if ($name === 'style') {
                    $style = mb_proposal_letter_clean_style($attr->value);
                    if ($style === '') $remove[] = $attr->name;
                    else $node->setAttribute('style', $style);
                    continue;
                }
                if ($name === 'class' && in_array($tag, $tableTags, true)) {
                    $node->setAttribute('class', preg_replace('/[^a-z0-9_\-\s]/i', '', $attr->value));
                    continue;
                }
                $remove[] = $attr->name;
            }
            foreach ($remove as $name) $node->removeAttribute($name);
        }
    }
    for ($child = $node->firstChild; $child; ) {
        $next = $child->nextSibling;
        mb_proposal_letter_sanitize_node($child, $doc, $allowedTags, $tableTags);
        $child = $next;
    }
}

function mb_proposal_letter_sanitize_html($html) {
    $html = (string)$html;
    $html = preg_replace('#<\s*(script|iframe|object|embed|form|input|button)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    $html = preg_replace('#<\s*(input|button)[^>]*\/?\s*>#is', '', $html);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/javascript\s*:/i', '', $html);

    if (!class_exists('DOMDocument')) {
        $allowed = '<p><br><strong><b><em><i><u><s><span><div><h1><h2><h3><h4><ul><ol><li><table><thead><tbody><tfoot><tr><th><td><hr>';
        return strip_tags($html, $allowed);
    }

    $allowedTags = ['p','br','strong','b','em','i','u','s','span','div','h1','h2','h3','h4','ul','ol','li','table','thead','tbody','tfoot','tr','th','td','hr'];
    $tableTags = ['table','thead','tbody','tfoot','tr','th','td'];
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="pl-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('pl-root');
    if (!$root) return '';
    mb_proposal_letter_sanitize_node($root, $doc, array_merge($allowedTags, ['div']), $tableTags);
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}
