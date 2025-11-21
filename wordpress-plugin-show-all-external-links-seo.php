<?php
/**
 * Plugin Name: Show External Links per Post for SEO
 * Description: Lists all published post URLs and the external links contained in each post. Output is available under Tools → External links per post. Optional plain text under ?format=txt.
 * Version: 1.4.0
 * Author: Dr. Wolfgang Sender
 * Author URI: https://life-in-germany.de/
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: show-external-links-seo
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page(
        'External links per post',
        'External links per post',
        'manage_options',
        'ext-links-per-post',
        'ext_links_per_post_render_page'
    );
});

/**
 * Render admin page
 */
function ext_links_per_post_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions.'));
    }

    $as_text = isset($_GET['format']) && strtolower(sanitize_text_field($_GET['format'])) === 'txt';
    if ($as_text) {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    $site_host = parse_url(home_url(), PHP_URL_HOST);

    if (!$as_text) {
        echo '<div class="wrap">';
        echo '<h1>External links per post</h1>';
        echo '<style>
                .extlinks-status { font-weight:600; padding:0 4px; border-radius:3px; }
                .ext-ok { color:#2e7d32; }
                .ext-redirect { color:#1976d2; }
                .ext-warn { color:#e65100; }
                .ext-bad { color:#c62828; }
                .ext-unknown { color:#6a6a6a; }
                .ext-footer { margin-top:16px; font-size:12px; color:#555; }
                .ext-footer a { text-decoration:none; }
              </style>';
        echo '<pre style="white-space:pre-wrap;line-height:1.45;">';
    }

    @set_time_limit(300);
    $paged = 1;
    $per_page = 150;
    $total_printed = 0;

    while (true) {
        $q = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'no_found_rows'  => false,
        ]);

        if (!$q->have_posts()) {
            break;
        }

        foreach ($q->posts as $post_id) {
            $permalink = get_permalink($post_id);
            $content   = get_post_field('post_content', $post_id);

            $links = ext_links_per_post_extract_external($content, $site_host);

            // Skip posts without external links
            if (empty($links)) {
                continue;
            }

            if ($as_text) {
                echo $permalink . PHP_EOL;
            } else {
                $edit_url = get_edit_post_link($post_id, '');
                if (!$edit_url) {
                    $edit_url = admin_url('post.php?post=' . intval($post_id) . '&action=edit');
                }
                $display  = esc_html($permalink);
                $edit_a   = '<a href="' . esc_url($edit_url) . '" target="_blank" rel="noopener">' . $display . '</a>';
                echo $edit_a . PHP_EOL;
            }

            foreach ($links as $u) {
                $status = ext_links_per_post_check_status($u);
                if ($as_text) {
                    echo ' - ' . $u . ' [status=' . ($status['code'] ?? 'n/a') . ']' . PHP_EOL;
                } else {
                    $cls = 'ext-unknown';
                    $code = isset($status['code']) ? intval($status['code']) : 0;
                    if ($code >= 200 && $code < 300) {
                        $cls = 'ext-ok';
                    } elseif ($code >= 300 && $code < 400) {
                        $cls = 'ext-redirect';
                    } elseif ($code == 404 || $code == 410) {
                        $cls = 'ext-warn';
                    } elseif ($code >= 400 && $code < 600) {
                        $cls = 'ext-bad';
                    }
                    $status_html = '<span class="extlinks-status ' . esc_attr($cls) . '">[' . ($code ? $code : 'n/a') . ']</span>';
                    echo ' - <a href="' . esc_url($u) . '" target="_blank" rel="noopener nofollow">' . esc_html($u) . '</a> ' . $status_html . PHP_EOL;
                }
            }

            echo PHP_EOL; // empty line after each post
            $total_printed++;
        }

        wp_reset_postdata();
        $paged++;
    }

    if (!$as_text) {
        echo '</pre>';
        echo '<p>Posts listed: ' . intval($total_printed) . '</p>';
        echo '<div class="ext-footer">';
        echo 'Author: <a href="https://www.linkedin.com/in/absender/" target="_blank" rel="noopener">Dr. Wolfgang Sender</a> - ';
        echo '<a href="https://life-in-germany.de/" target="_blank" rel="noopener">Life-in-Germany.de</a><br>';
        echo 'License: <a href="https://opensource.org/licenses/MIT" target="_blank" rel="noopener">MIT</a>. ';
        echo 'Disclaimer: Use at your own risk. No warranty. HTTP checks may be slow or blocked by target sites.';
        echo '</div>';
        echo '</div>';
    }
}

/**
 * Check external URL status by HEAD request, fallback to GET on 405.
 *
 * @param string $url
 * @return array{code:int|0, final:string}|array
 */
function ext_links_per_post_check_status($url) {
    $args = [
        'timeout'     => 5,
        'redirection' => 5,
        'user-agent'  => 'WP-ExtLinks-SEO/1.0; ' . home_url('/'),
    ];

    $res = wp_remote_head($url, $args);
    if (is_wp_error($res)) {
        // try GET as fallback
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            return ['code' => 0, 'final' => ''];
        }
    } else {
        $code = wp_remote_retrieve_response_code($res);
        // Some servers reject HEAD with 405 - try GET
        if ($code === 405) {
            $res = wp_remote_get($url, $args);
            if (is_wp_error($res)) {
                return ['code' => 0, 'final' => ''];
            }
        }
    }

    $code = intval(wp_remote_retrieve_response_code($res));
    $final = wp_remote_retrieve_header($res, 'x-final-url');
    if (!$final) {
        if (isset($res['http_response']) && is_object($res['http_response'])) {
            $transport = $res['http_response']->get_response_object();
            if (isset($transport->url)) {
                $final = $transport->url;
            }
        }
    }
    return ['code' => $code, 'final' => $final];
}

/**
 * Extract external links from HTML content.
 */
function ext_links_per_post_extract_external($html, $site_host) {
    $out = [];

    if (!is_string($html) || $html === '') {
        return $out;
    }

    $internalErrors = libxml_use_internal_errors(true);
    $dom = new DOMDocument();

    $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>'
             . $html
             . '</body></html>';

    if (@$dom->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR)) {
        $anchors = $dom->getElementsByTagName('a');
        foreach ($anchors as $a) {
            $href = $a->getAttribute('href');
            if (!$href) {
                continue;
            }
            $href = trim($href);

            $lower = strtolower($href);
            if ($lower === '' || $lower[0] === '#') {
                continue;
            }
            if (strpos($lower, 'mailto:') === 0) {
                continue;
            }
            if (strpos($lower, 'tel:') === 0) {
                continue;
            }
            if (strpos($lower, 'javascript:') === 0) {
                continue;
            }

            $p = @parse_url($href);
            if ($p === false) {
                continue;
            }

            if (!isset($p['host'])) {
                if (isset($href[0]) && $href[0] === '/') {
                    continue;
                }
                $firstTwo = substr($href, 0, 2);
                if ($firstTwo === './' || $firstTwo === '..' || $firstTwo === '../') {
                    continue;
                }
                continue;
            }

            $host = strtolower($p['host']);
            $site_host_l = strtolower((string)$site_host);
            if ($host === $site_host_l) {
                continue;
            }

            $normalized = ext_links_per_post_normalize_url($href);
            if ($normalized !== '' && !in_array($normalized, $out, true)) {
                $out[] = $normalized;
            }
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);

    return $out;
}

/**
 * Simple URL normalization
 */
function ext_links_per_post_normalize_url($url) {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = @parse_url($url);
    if ($parts === false) {
        return $url;
    }
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host   = isset($parts['host']) ? $parts['host'] : '';
    $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path   = isset($parts['path']) ? $parts['path'] : '';
    $query  = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

    $path = preg_replace('#/{2,}#', '/', $path);

    return $scheme . $host . $port . $path . $query;
}
