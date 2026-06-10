<?php
function mb_proposal_letter_sanitize_html($html) {
    $html = preg_replace('#<\s*script[^>]*>.*?<\s*/\s*script\s*>#is', '', (string)$html);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/javascript\s*:/i', '', $html);
    $allowed = '<p><br><strong><b><em><i><u><span><div><h1><h2><h3><ul><ol><li><table><thead><tbody><tr><th><td><hr><section>';
    return strip_tags($html, $allowed);
}
