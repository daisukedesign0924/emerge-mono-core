<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function emcore_apply_text( $html, $key, $value ) {
	if ( $value === null ) {
		return $html;
	}
	$qkey = preg_quote( $key, '/' );
	$html = preg_replace_callback(
		'/<([a-zA-Z0-9]+)([^>]*data-em-key=["\']' . $qkey . '["\'][^>]*)>(.*?)<\/\1>/is',
		function ( $m ) use ( $value ) {
			$tag   = $m[1];
			$attrs = $m[2];
			$inner = $m[3];
			if ( preg_match( '/<(script|style)/i', $tag ) ) {
				return $m[0];
			}
			if ( preg_match( '/data-em-editable=["\'](image|bg|href)["\']/', $attrs ) ) {
				return $m[0];
			}
			if ( preg_match( '/<img\b/i', $inner ) ) {
				return $m[0];
			}
			if ( preg_match( '/<(?!\/?(span|b|strong|em|i|br|wbr)\b)/i', $inner ) ) {
				return $m[0];
			}
			return '<' . $tag . $attrs . '>' . $value . '</' . $tag . '>';
		},
		$html
	);
	$html = str_replace( '{{' . $key . '}}', $value, $html );
	$html = str_replace( '{{field:' . $key . '}}', $value, $html );
	return $html;
}

function emcore_apply_font_family( $html, $key, $family ) {
	$family = emcore_sanitize_font_family( $family );
	if ( $family === '' ) { return $html; }
	$qkey = preg_quote( $key, '/' );
	$css  = esc_attr( $family );
	return preg_replace_callback(
		'/<([a-zA-Z0-9]+)([^>]*data-em-key=["\']' . $qkey . '["\'][^>]*)>/i',
		function ( $m ) use ( $css ) {
			$attrs = $m[2];
			if ( preg_match( '/\sstyle=(["\'])(.*?)\1/i', $attrs ) ) {
				$attrs = preg_replace( '/(\sstyle=(["\']))(.*?)(\2)/i', '$1font-family:' . $css . ';$3$4', $attrs, 1 );
			} else {
				$attrs .= ' style="font-family:' . $css . ';"';
			}
			return '<' . $m[1] . $attrs . '>';
		},
		$html
	);
}

function emcore_parse_image_variants( $url ) {
	if ( is_array( $url ) ) {
		return $url;
	}
	$trim = trim( (string) $url );
	if ( $trim && $trim[0] === '{' ) {
		$dec = json_decode( $trim, true );
		if ( is_array( $dec ) ) {
			return $dec;
		}
	}
	return array( 'default' => $trim );
}

function emcore_apply_image( $html, $key, $url ) {
	if ( $url === '' ) {
		return $html;
	}
	$vars    = emcore_parse_image_variants( $url );
	$default = isset( $vars['default'] ) ? $vars['default'] : ( isset( $vars['src'] ) ? $vars['src'] : '' );
	$sources = isset( $vars['sources'] ) && is_array( $vars['sources'] ) ? $vars['sources'] : array();
	if ( $default === '' && $sources ) {
		$default = reset( $sources );
	}
	if ( $default === '' ) {
		return $html;
	}
	$url  = $default;
	$qkey = preg_quote( $key, '/' );
	// picture block marked as a whole
	$html = preg_replace_callback(
		'/<picture([^>]*data-em-key=["\']' . $qkey . '["\'][^>]*)>(.*?)<\/picture>/is',
		function ( $m ) use ( $default, $sources ) {
			$inner = $m[2];
			if ( $sources ) {
				foreach ( $sources as $media => $src ) {
					$media_q = preg_quote( $media, '/' );
					$src_e   = esc_url( $src );
					$inner   = preg_replace(
						'/<source([^>]*media=["\']' . $media_q . '["\'][^>]*)>/i',
						'<source$1 srcset="' . $src_e . '">',
						$inner
					);
					$inner = preg_replace(
						'/(<source[^>]*media=["\']' . $media_q . '["\'][^>]*)\ssrcset=["\'][^"\']*["\']/i',
						'$1 srcset="' . $src_e . '"',
						$inner
					);
				}
			}
			$inner = preg_replace( '/(<img[^>]*?)\ssrc=["\'][^"\']*["\']/i', '$1 src="' . esc_url( $default ) . '"', $inner );
			return '<picture' . $m[1] . '>' . $inner . '</picture>';
		},
		$html
	);

	$html = preg_replace_callback(
		'/<img([^>]*data-em-key=["\']' . $qkey . '["\'][^>]*)>/i',
		function ( $m ) use ( $url ) {
			$tag = $m[0];
			if ( preg_match( '/\ssrc=/i', $tag ) ) {
				$tag = preg_replace( '/\ssrc=["\'][^"\']*["\']/', ' src="' . esc_url( $url ) . '"', $tag );
			} else {
				$tag = preg_replace( '/<img/i', '<img src="' . esc_url( $url ) . '"', $tag );
			}
			return $tag;
		},
		$html
	);
	$html = preg_replace_callback(
		'/<([^>]*data-em-key=["\']' . $qkey . '["\'][^>]*)>/i',
		function ( $m ) use ( $url ) {
			$tag = $m[0];
			if ( stripos( $tag, '<img' ) === 0 ) {
				return $tag;
			}
			if ( preg_match( '/background-image\s*:\s*url\([^)]*\)/i', $tag ) ) {
				$tag = preg_replace( '/background-image\s*:\s*url\([^)]*\)/i', 'background-image:url(' . esc_url( $url ) . ')', $tag );
			} elseif ( preg_match( '/style="/', $tag ) ) {
				$tag = preg_replace( '/style="/', 'style="background-image:url(' . esc_url( $url ) . ');', $tag, 1 );
			}
			return $tag;
		},
		$html
	);
	$html = str_replace( '{{' . $key . '}}', esc_url( $url ), $html );
	$html = str_replace( '{{field:' . $key . '}}', esc_url( $url ), $html );
	$html = str_replace( '{{thumbnail_url}}', esc_url( $url ), $html );
	return $html;
}

function emcore_apply_href( $html, $key, $url ) {
	if ( $url === '' ) {
		return $html;
	}
	$safe = esc_url( $url );
	if ( ! $safe ) {
		return $html;
	}
	$qkey = preg_quote( $key, '/' );
	$html = preg_replace_callback(
		'/<([a-zA-Z0-9]+)([^>]*data-em-key=["\']' . $qkey . '["\'][^>]*)>/i',
		function ( $m ) use ( $safe ) {
			$full = $m[0];
			$tag  = strtolower( $m[1] );
			$edit = '';
			if ( preg_match( '/data-em-editable=["\']([^"\']+)["\']/', $full, $em ) ) {
				$edit = $em[1];
			}
			if ( $edit && ! in_array( $edit, array( 'href', 'url', 'link' ), true ) && $tag !== 'a' ) {
				return $full;
			}
			if ( preg_match( '/\shref=/i', $full ) ) {
				return preg_replace( '/\shref=["\'][^"\']*["\']/', ' href="' . $safe . '"', $full );
			}
			if ( $tag === 'a' ) {
				return preg_replace( '/<a/i', '<a href="' . $safe . '"', $full );
			}
			return $full;
		},
		$html
	);
	return $html;
}

function emcore_apply_slides( $html, $key, $urls ) {
	if ( ! is_array( $urls ) ) {
		$dec = json_decode( (string) $urls, true );
		$urls = is_array( $dec ) ? $dec : array();
	}
	$urls = array_values( array_filter( array_map( 'esc_url', $urls ) ) );
	$html = emcore_protect_raw_html_blocks( $html, $raw_blocks );
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//*[@data-em-key="' . $key . '"]' );
	foreach ( $nodes as $box ) {
		$kind = $box->getAttribute( 'data-em-editable' );
		if ( $kind && ! in_array( $kind, array( 'slides', 'slider', 'gallery' ), true ) ) {
			continue;
		}
		$slides = array();
		foreach ( $box->childNodes as $child ) {
			if ( $child instanceof DOMElement && preg_match( '/(?:^|\s)(?:emcore-slider-item|slider-slide|slide|swiper-slide|splide__slide|slick-slide)(?:\s|$)/i', $child->getAttribute( 'class' ) ) ) {
				$slides[] = $child;
			}
		}
		if ( empty( $slides ) ) {
			foreach ( $box->childNodes as $child ) { if ( $child instanceof DOMElement ) { $slides[] = $child; } }
		}
		if ( empty( $slides ) && $box->parentNode ) {
			$box = $box->parentNode;
			$slides = array();
			foreach ( $box->childNodes as $child ) {
				if ( $child instanceof DOMElement ) {
					$slides[] = $child;
				}
			}
		}
		if ( empty( $slides ) ) {
			continue;
		}
		$tpl = $slides[0]->cloneNode( true );
		foreach ( $slides as $s ) {
			if ( $s->parentNode ) {
				$s->parentNode->removeChild( $s );
			}
		}
		foreach ( $urls as $index => $url ) {
			$clone = $tpl->cloneNode( true );
			/*
			 * The first slide is commonly the active design sample. Cloning that
			 * node byte-for-byte used to copy its active state to every slide, so
			 * all images were stacked until the site's JavaScript advanced once.
			 * Keep the source state on the first item only and clear common slider
			 * state classes from subsequent clones.
			 */
			if ( $index > 0 && $clone instanceof DOMElement ) {
				$state_classes = array(
					'active',
					'is-active',
					'current',
					'is-current',
					'swiper-slide-active',
					'slick-active',
					'splide__slide--active',
				);
				$classes = preg_split( '/\s+/', trim( $clone->getAttribute( 'class' ) ) );
				$classes = array_values( array_diff( array_filter( $classes ), $state_classes ) );
				$clone->setAttribute( 'class', implode( ' ', $classes ) );
				$clone->removeAttribute( 'aria-current' );
			}
			$imgs  = $clone->getElementsByTagName( 'img' );
			if ( $imgs->length ) {
				$imgs->item( 0 )->setAttribute( 'src', $url );
			}
			$walker = $clone->getElementsByTagName( '*' );
			$els    = array( $clone );
			foreach ( $walker as $el ) {
				$els[] = $el;
			}
			foreach ( $els as $el ) {
				$style = $el->getAttribute( 'style' );
				if ( $style && preg_match( '/background-image\s*:\s*url\([^)]*\)/i', $style ) ) {
					$el->setAttribute( 'style', preg_replace( '/background-image\s*:\s*url\([^)]*\)/i', 'background-image:url(' . $url . ')', $style ) );
				}
			}
			$box->appendChild( $clone );
		}
		// Keep common dot navigation in sync with the number of slides.
		$dots = $xpath->query( '//*[@id="slider-dots" or contains(concat(" ",normalize-space(@class)," ")," slider-dots ") or contains(@class,"swiper-pagination")][1]' );
		if ( $dots->length ) {
			$dot_box = $dots->item( 0 ); $dot_tpl = null;
			foreach ( iterator_to_array( $dot_box->childNodes ) as $dot ) {
				if ( $dot instanceof DOMElement ) { if ( ! $dot_tpl ) { $dot_tpl = $dot->cloneNode( true ); } $dot_box->removeChild( $dot ); }
			}
			if ( $dot_tpl ) {
				foreach ( $urls as $index => $_url ) {
					$dot = $dot_tpl->cloneNode( true ); $dot->setAttribute( 'data-index', (string) $index ); $dot->setAttribute( 'aria-label', 'スライド ' . ( $index + 1 ) );
					$classes = preg_replace( '/\s*active\b/', '', $dot->getAttribute( 'class' ) ); if ( $index === 0 ) { $classes .= ' active'; } $dot->setAttribute( 'class', trim( $classes ) ); $dot_box->appendChild( $dot );
				}
			}
		}
	}
	$out = $dom->saveHTML();
	$out = preg_replace( '/^<\?xml[^>]*>/', '', $out );
	return emcore_restore_raw_html_blocks( $out, $raw_blocks );
}

function emcore_apply_accordion( $html, $key, $items ) {
	if ( ! is_array( $items ) ) { $items = json_decode( (string) $items, true ); }
	if ( ! is_array( $items ) ) { return $html; }
	$html = emcore_protect_raw_html_blocks( $html, $raw_blocks );
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
	$xpath = new DOMXPath( $dom );
	foreach ( $xpath->query( '//*[@data-em-key="' . $key . '"]' ) as $box ) {
		if ( $box->getAttribute( 'data-em-editable' ) !== 'accordion' ) { continue; }
		$prototypes = $xpath->query( './/*[@data-em-accordion-item="1"]', $box );
		$prototype = $prototypes->length ? $prototypes->item( 0 ) : null;
		if ( ! $prototype ) { continue; }
		$parent = $prototype->parentNode;
		foreach ( iterator_to_array( $prototypes ) as $old ) { if ( $old->parentNode ) { $old->parentNode->removeChild( $old ); } }
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$clone = $prototype->cloneNode( true );
			$q = $xpath->query( './/*[@data-em-accordion-question="1"]', $clone );
			$a = $xpath->query( './/*[@data-em-accordion-answer="1"]', $clone );
			if ( $q->length ) { while ( $q->item( 0 )->firstChild ) { $q->item( 0 )->removeChild( $q->item( 0 )->firstChild ); } $q->item( 0 )->appendChild( $dom->createTextNode( (string) ( $item['question'] ?? '' ) ) ); }
			if ( $a->length ) { while ( $a->item( 0 )->firstChild ) { $a->item( 0 )->removeChild( $a->item( 0 )->firstChild ); } $a->item( 0 )->appendChild( $dom->createTextNode( (string) ( $item['answer'] ?? '' ) ) ); }
			$parent->appendChild( $clone );
		}
	}
	$out = $dom->saveHTML();
	$out = preg_replace( '/^<\?xml[^>]*>/', '', $out );
	return emcore_restore_raw_html_blocks( $out, $raw_blocks );
}

function emcore_apply_menu( $html, $key, $items ) {
	if ( ! is_array( $items ) ) { $items = json_decode( (string) $items, true ); }
	if ( ! is_array( $items ) ) { return $html; }
	$html = emcore_protect_raw_html_blocks( $html, $raw_blocks );
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
	$xpath = new DOMXPath( $dom );
	foreach ( $xpath->query( '//*[@data-em-key="' . $key . '"]' ) as $box ) {
		if ( $box->getAttribute( 'data-em-editable' ) !== 'menu' ) { continue; }
		$prototypes = $xpath->query( './/*[@data-em-menu-item="1"]', $box );
		$prototype = $prototypes->length ? $prototypes->item( 0 ) : null;
		if ( ! $prototype ) { continue; }
		$prototype_list = iterator_to_array( $prototypes );
		$parent = $prototype->parentNode;
		foreach ( iterator_to_array( $prototypes ) as $old ) {
			if ( $old->parentNode ) { $old->parentNode->removeChild( $old ); }
		}
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$label = sanitize_text_field( $item['label'] ?? '' );
			$url = esc_url( $item['url'] ?? '' );
			if ( '' === $label && '' === $url ) { continue; }
			$source = isset( $prototype_list[ $index ] ) ? $prototype_list[ $index ] : $prototype;
			$clone = $source->cloneNode( true );
			if ( ! isset( $prototype_list[ $index ] ) && $clone instanceof DOMElement ) {
				$classes = preg_split( '/\s+/', trim( $clone->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
				$classes = array_values( array_filter( $classes, function ( $class ) { return ! preg_match( '/(?:^|[-_])(active|current|selected)(?:$|[-_])/', $class ); } ) );
				$clone->setAttribute( 'class', implode( ' ', $classes ) );
				$clone->removeAttribute( 'aria-current' );
			}
			$link = strtolower( $clone->tagName ) === 'a' ? $clone : $xpath->query( './/a', $clone )->item( 0 );
			if ( ! $link instanceof DOMElement ) { continue; }
			$link->setAttribute( 'href', $url ?: '#' );
			$target = ( $item['target'] ?? '' ) === '_blank' ? '_blank' : '';
			if ( $target ) { $link->setAttribute( 'target', '_blank' ); $link->setAttribute( 'rel', 'noopener noreferrer' ); }
			else { $link->removeAttribute( 'target' ); $link->removeAttribute( 'rel' ); }
			$label_node = $xpath->query( './/*[@data-em-menu-label="1"]', $clone )->item( 0 );
			if ( ! $label_node && strtolower( $link->tagName ) === 'a' ) { $label_node = $link; }
			if ( $label_node ) {
				while ( $label_node->firstChild ) { $label_node->removeChild( $label_node->firstChild ); }
				$label_node->appendChild( $dom->createTextNode( $label ) );
			}
			$parent->appendChild( $clone );
		}
	}
	$out = $dom->saveHTML();
	$out = preg_replace( '/^<\?xml[^>]*>/', '', $out );
	return emcore_restore_raw_html_blocks( $out, $raw_blocks );
}

function emcore_dynamic_marked_node( $xpath, $root, $attribute ) {
	if ( $root instanceof DOMElement && $root->getAttribute( $attribute ) === '1' ) { return $root; }
	$nodes = $xpath->query( './/*[@' . $attribute . '="1"]', $root );
	return $nodes && $nodes->length ? $nodes->item( 0 ) : null;
}

function emcore_dynamic_set_text( $dom, $node, $value ) {
	if ( ! $node ) { return; }
	while ( $node->firstChild ) { $node->removeChild( $node->firstChild ); }
	$node->appendChild( $dom->createTextNode( (string) $value ) );
}

/** Render list, steps, gallery, tabs, table and generic card repeaters from their first complete samples. */
function emcore_apply_collection( $html, $key, $type, $items ) {
	if ( ! is_array( $items ) ) { $items = json_decode( (string) $items, true ); }
	if ( ! is_array( $items ) ) { return $html; }
	$markers = array(
		'list' => 'data-em-list-item', 'steps' => 'data-em-step-item', 'gallery' => 'data-em-gallery-item',
		'tabs' => 'data-em-tab-item', 'table' => 'data-em-table-row', 'repeater' => 'data-em-repeater-item',
	);
	if ( empty( $markers[ $type ] ) ) { return $html; }
	$html = emcore_protect_raw_html_blocks( $html, $raw_blocks );
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD );
	$xpath = new DOMXPath( $dom );
	foreach ( $xpath->query( '//*[@data-em-key="' . $key . '"]' ) as $box ) {
		if ( $box->getAttribute( 'data-em-editable' ) !== $type ) { continue; }
		$prototypes = $xpath->query( './/*[@' . $markers[ $type ] . '="1"]', $box );
		if ( ! $prototypes->length ) { continue; }
		$prototype_list = iterator_to_array( $prototypes );
		$prototype = $prototype_list[0]; $parent = $prototype->parentNode;
		foreach ( $prototype_list as $old ) { if ( $old->parentNode ) { $old->parentNode->removeChild( $old ); } }
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { $item = array( 'text' => (string) $item ); }
			$source = $prototype_list[ $index ] ?? $prototype;
			$clone = $source->cloneNode( true );
			if ( 'list' === $type ) {
				emcore_dynamic_set_text( $dom, emcore_dynamic_marked_node( $xpath, $clone, 'data-em-list-text' ) ?: $clone, $item['text'] ?? '' );
			} elseif ( 'steps' === $type ) {
				emcore_dynamic_set_text( $dom, emcore_dynamic_marked_node( $xpath, $clone, 'data-em-step-index' ), $index + 1 );
				emcore_dynamic_set_text( $dom, emcore_dynamic_marked_node( $xpath, $clone, 'data-em-step-title' ), $item['title'] ?? '' );
				emcore_dynamic_set_text( $dom, emcore_dynamic_marked_node( $xpath, $clone, 'data-em-step-description' ), $item['description'] ?? '' );
			} elseif ( 'gallery' === $type ) {
				$image = emcore_dynamic_marked_node( $xpath, $clone, 'data-em-gallery-image' );
				if ( $image ) { $image->setAttribute( 'src', esc_url( $item['image'] ?? '' ) ); $image->setAttribute( 'alt', sanitize_text_field( $item['alt'] ?? '' ) ); }
				$link = emcore_dynamic_marked_node( $xpath, $clone, 'data-em-gallery-link' );
				if ( $link ) { $link->setAttribute( 'href', esc_url( $item['url'] ?? '' ) ?: '#' ); }
			} elseif ( 'tabs' === $type ) {
				$trigger = emcore_dynamic_marked_node( $xpath, $clone, 'data-em-tab-trigger' );
				$content = emcore_dynamic_marked_node( $xpath, $clone, 'data-em-tab-content' );
				emcore_dynamic_set_text( $dom, $trigger, $item['label'] ?? '' ); emcore_dynamic_set_text( $dom, $content, $item['content'] ?? '' );
				$id = sanitize_key( $key ) . '-panel-' . ( $index + 1 ); $trigger_id = sanitize_key( $key ) . '-tab-' . ( $index + 1 );
				if ( $trigger ) { $trigger->setAttribute( 'id', $trigger_id ); $trigger->setAttribute( 'aria-controls', $id ); $trigger->setAttribute( 'aria-selected', $index === 0 ? 'true' : 'false' ); }
				if ( $content ) { $content->setAttribute( 'id', $id ); $content->setAttribute( 'aria-labelledby', $trigger_id ); if ( $index ) { $content->setAttribute( 'hidden', 'hidden' ); } else { $content->removeAttribute( 'hidden' ); } }
			} elseif ( 'table' === $type ) {
				$cells = $xpath->query( './/*[@data-em-table-cell="1"]', $clone );
				foreach ( $cells as $cell_index => $cell ) { emcore_dynamic_set_text( $dom, $cell, $item['cells'][ $cell_index ] ?? '' ); }
			} elseif ( 'repeater' === $type ) {
				$fields = $xpath->query( './/*[@data-em-repeater-field]', $clone );
				foreach ( $fields as $field_index => $field_node ) {
					$field_key = sanitize_key( $field_node->getAttribute( 'data-em-repeater-field' ) ) ?: 'field_' . ( $field_index + 1 );
					$value = $item[ $field_key ] ?? '';
					if ( strtolower( $field_node->tagName ) === 'img' ) { $field_node->setAttribute( 'src', esc_url( $value ) ); }
					elseif ( strtolower( $field_node->tagName ) === 'a' ) { $field_node->setAttribute( 'href', esc_url( $value ) ?: '#' ); }
					else { emcore_dynamic_set_text( $dom, $field_node, $value ); }
				}
			}
			$parent->appendChild( $clone );
		}
	}
	$out = preg_replace( '/^<\?xml[^>]*>/', '', $dom->saveHTML() );
	return emcore_restore_raw_html_blocks( $out, $raw_blocks );
}

function emcore_apply_field_value( $html, $field, $val ) {
	if ( $field['type'] === 'image' ) {
		return emcore_apply_image( $html, $field['key'], $val );
	}
	if ( $field['type'] === 'url' ) {
		return emcore_apply_href( $html, $field['key'], $val );
	}
	if ( $field['type'] === 'slides' ) {
		return emcore_apply_slides( $html, $field['key'], $val );
	}
	if ( $field['type'] === 'accordion' ) {
		return emcore_apply_accordion( $html, $field['key'], $val );
	}
	if ( $field['type'] === 'menu' ) {
		return emcore_apply_menu( $html, $field['key'], $val );
	}
	if ( in_array( $field['type'], array( 'list', 'steps', 'gallery', 'tabs', 'table', 'repeater' ), true ) ) {
		return emcore_apply_collection( $html, $field['key'], $field['type'], $val );
	}
	return emcore_apply_text( $html, $field['key'], $val );
}

function emcore_apply_shared_to_html( $html, $tpl_id = '' ) {
	$fields = emcore_fields_from_html( $html );
	$site   = emcore_get_site_shared();
	$tpls   = $tpl_id ? emcore_get_tpl_shared( $tpl_id ) : array();
	foreach ( $fields as $field ) {
		$scope = isset( $field['scope'] ) ? $field['scope'] : 'item';
		$values = 'site' === $scope ? $site : ( 'template' === $scope ? $tpls : array() );
		if ( ! $values ) {
			continue;
		}
		if ( isset( $values[ $field['key'] ] ) && $values[ $field['key'] ] !== '' ) {
			$html = emcore_apply_field_value( $html, $field, $values[ $field['key'] ] );
		}
		// A font can be changed while keeping the designer's original text/link value.
		if ( ! empty( $field['font_editable'] ) ) {
			$html = emcore_apply_font_family( $html, $field['key'], $values[ $field['key'] . '__font' ] ?? '' );
		}
	}
	return $html;
}

function emcore_render_post_into_html( $html, $post ) {
	if ( ! $post ) {
		return $html;
	}
	$tpl_id = '';
	if ( $post->post_type === 'page' ) {
		$tpl_id = emcore_template_id_for_page( $post );
	} else {
		$tpl_id = emcore_cpt_single_template_id( $post->post_type );
	}
	$html   = emcore_apply_shared_to_html( $html, $tpl_id );
	$fields = emcore_fields_from_html( $html );
	foreach ( $fields as $field ) {
		$scope = isset( $field['scope'] ) ? $field['scope'] : 'item';
		if ( $scope !== 'item' ) {
			continue;
		}
		$val = emcore_get_post_field_value( $post, $field );
		if ( ! empty( $field['font_editable'] ) ) {
			$html = emcore_apply_font_family( $html, $field['key'], get_post_meta( $post->ID, '_emcore_' . $field['key'] . '_font', true ) );
		}
		// An unset field keeps the complete sample content supplied by the designer.
		if ( $val === '' || $val === null || $val === '[]' ) {
			continue;
		}
		if ( $field['type'] === 'image' ) {
			$html = emcore_apply_image( $html, $field['key'], $val );
		} elseif ( $field['type'] === 'url' ) {
			$html = emcore_apply_href( $html, $field['key'], $val );
		} elseif ( $field['type'] === 'slides' ) {
			$html = emcore_apply_slides( $html, $field['key'], $val );
		} elseif ( $field['type'] === 'accordion' ) {
			$html = emcore_apply_accordion( $html, $field['key'], $val );
		} elseif ( $field['type'] === 'menu' ) {
			$html = emcore_apply_menu( $html, $field['key'], $val );
		} elseif ( in_array( $field['type'], array( 'list', 'steps', 'gallery', 'tabs', 'table', 'repeater' ), true ) ) {
			$html = emcore_apply_collection( $html, $field['key'], $field['type'], $val );
		} else {
			$html = emcore_apply_text( $html, $field['key'], $val );
		}
	}
	$html = str_replace( '{{title}}', esc_html( $post->post_title ), $html );
	$html = str_replace( '{{content}}', wp_kses_post( $post->post_content ), $html );
	$html = str_replace( '{{excerpt}}', esc_html( $post->post_excerpt ), $html );
	$html = str_replace( '{{permalink}}', esc_url( get_permalink( $post ) ), $html );
	$html = str_replace( '{{date}}', esc_html( get_the_date( '', $post ) ), $html );
	$thumb = get_the_post_thumbnail_url( $post, 'full' );
	if ( $thumb ) {
		$html = str_replace( '{{thumbnail_url}}', esc_url( $thumb ), $html );
		$html = str_replace( '{{thumbnail}}', '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $post->post_title ) . '">', $html );
	}
	return $html;
}

function emcore_strip_editor_chrome( $html ) {
	$html = preg_replace( '/<style[^>]*>[^<]*data-em-editable[^<]*<\/style>/i', '', $html );
	$html = preg_replace( '/\.emcore-layer-focus\{[^}]*\}/', '', $html );
	$html = preg_replace( '/\[data-em-editable\]\{[^}]*\}/', '', $html );
	$html = str_replace( '\\n', '', $html );
	return $html;
}

function emcore_inject_admin_bar( $html ) {
	if ( is_admin() || ! is_user_logged_in() || ! is_admin_bar_showing() ) {
		return $html;
	}
	if ( ! function_exists( 'wp_admin_bar_render' ) ) {
		return $html;
	}
	if ( empty( $GLOBALS['wp_admin_bar'] ) && function_exists( '_wp_admin_bar_init' ) ) {
		_wp_admin_bar_init();
	}
	ob_start();
	wp_admin_bar_render();
	$bar = ob_get_clean();
	if ( ! $bar ) {
		return $html;
	}
	// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- A complete stored HTML document is emitted directly, outside wp_head().
	$css  = '<link rel="stylesheet" href="' . esc_url( includes_url( 'css/dashicons.min.css' ) ) . '">';
	// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- A complete stored HTML document is emitted directly, outside wp_head().
	$css .= '<link rel="stylesheet" href="' . esc_url( includes_url( 'css/admin-bar.min.css' ) ) . '">';
	$css .= '<style>html{margin-top:32px !important}#wpadminbar{position:fixed;top:0;left:0;right:0;z-index:99999}</style>';
	if ( stripos( $html, '</head>' ) !== false ) {
		$html = str_ireplace( '</head>', $css . '</head>', $html );
	} else {
		$html = $css . $html;
	}
	if ( preg_match( '/<html[^>]*>/i', $html ) ) {
		$html = preg_replace( '/<html([^>]*)class="/i', '<html$1class="wp-toolbar ', $html, 1, $count );
		if ( ! $count ) {
			$html = preg_replace( '/<html([^>]*)>/i', '<html$1 class="wp-toolbar">', $html, 1 );
		}
	}
	if ( stripos( $html, '</body>' ) !== false ) {
		$html = str_ireplace( '</body>', $bar . '</body>', $html );
	} else {
		$html .= $bar;
	}
	return $html;
}

function emcore_output_html( $html ) {
	$html = emcore_strip_editor_chrome( $html );
	$post = get_queried_object();
	$tpl_id = $post instanceof WP_Post ? get_post_meta( $post->ID, '_emcore_tpl_id', true ) : '';
	$kind = $post instanceof WP_Post ? $post->post_type : '';
	$html = emcore_apply_document_extensions( $html, emcore_document_context( $post, $tpl_id, $kind ) );
	if ( function_exists( 'wp_print_font_faces' ) ) {
		ob_start();
		wp_print_font_faces();
		$font_faces = trim( ob_get_clean() );
		if ( $font_faces !== '' ) {
			$html = stripos( $html, '</head>' ) !== false ? str_ireplace( '</head>', $font_faces . '</head>', $html ) : $font_faces . $html;
		}
	}
	$html = emcore_inject_admin_bar( $html );
	if ( '404' !== get_query_var( 'emcore_system_page' ) ) { status_header( 200 ); }
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core intentionally renders the administrator-approved complete HTML template; escaping would corrupt the document.
	echo $html;
	exit;
}

function emcore_archive_filter_bar( $cpt, $posts ) {
	$cpts = emcore_get_cpts();
	if ( isset( $cpts[ $cpt ]['filter_enabled'] ) && empty( $cpts[ $cpt ]['filter_enabled'] ) ) {
		return '';
	}
	if ( ! apply_filters( 'emcore_archive_filter_enabled', true, $cpt ) ) {
		return '';
	}
	$tax = $cpt . '_cat';
	if ( ! taxonomy_exists( $tax ) ) {
		return '';
	}
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}
	$out  = '<div class="emcore-cat-filter" data-em-filter="1">';
	$out .= '<button type="button" class="is-on" data-cat="">すべて</button>';
	foreach ( $terms as $term ) {
		$out .= '<button type="button" data-cat="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</button>';
	}
	$out .= '</div><style>.emcore-cat-filter{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:0 0 28px}.emcore-cat-filter button{border:1px solid #777;background:transparent;color:inherit;border-radius:999px;padding:8px 16px;cursor:pointer;font:inherit;font-size:13px}.emcore-cat-filter button.is-on{background:#111;color:#fff;border-color:#111}[data-em-card].is-hidden{display:none!important}</style>';
	$out .= '<script>(function(){var bar=document.querySelector("[data-em-filter]");if(!bar)return;bar.addEventListener("click",function(e){var b=e.target.closest("button");if(!b)return;bar.querySelectorAll("button").forEach(function(x){x.classList.toggle("is-on",x===b);});var cat=b.getAttribute("data-cat")||"";document.querySelectorAll("[data-em-card]").forEach(function(card){var cats=card.getAttribute("data-em-cats")||"";card.classList.toggle("is-hidden",!!cat&&cats.split(/\s+/).indexOf(cat)<0);});});})();</script>';
	return apply_filters( 'emcore_archive_filter_html', $out, $cpt, $terms );
}

function emcore_render_archive_card( $chunk, $post ) {
	$thumb = get_the_post_thumbnail_url( $post, 'large' );
	// Attribute-based cards retain real sample content in the source HTML.
	$values = array(
		'title' => esc_html( $post->post_title ), 'permalink' => esc_url( get_permalink( $post ) ),
		'excerpt' => esc_html( $post->post_excerpt ), 'date' => esc_html( get_the_date( '', $post ) ),
	);
	$chunk = preg_replace_callback(
		'/<([a-z][a-z0-9-]*)([^>]*data-em-post-field=["\'](title|excerpt|date|meta:[a-z0-9_-]+)["\'][^>]*)>(.*?)<\/\1>/is',
		function ( $m ) use ( $post, $values ) {
			$field = strtolower( $m[3] );
			$value = $values[ $field ] ?? '';
			if ( strpos( $field, 'meta:' ) === 0 ) {
				$key = substr( $field, 5 );
				$value = get_post_meta( $post->ID, '_emcore_' . $key, true );
				if ( $value === '' ) { $value = get_post_meta( $post->ID, $key, true ); }
				$value = is_scalar( $value ) ? esc_html( (string) $value ) : '';
			}
			return $value === '' ? $m[0] : '<' . $m[1] . $m[2] . '>' . $value . '</' . $m[1] . '>';
		},
		$chunk
	);
	$chunk = preg_replace_callback( '/<a([^>]*data-em-post-field=["\']permalink["\'][^>]*)>/i', function ( $m ) use ( $post ) {
		$tag = '<a' . $m[1] . '>'; $url = esc_url( get_permalink( $post ) );
		return preg_match( '/\shref=/i', $tag ) ? preg_replace( '/\shref=["\'][^"\']*["\']/', ' href="' . $url . '"', $tag ) : '<a href="' . $url . '"' . $m[1] . '>';
	}, $chunk );
	if ( $thumb ) {
		$chunk = preg_replace_callback( '/<img([^>]*data-em-post-field=["\']thumbnail(?:_url)?["\'][^>]*)>/i', function ( $m ) use ( $thumb, $post ) {
			$tag = '<img' . $m[1] . '>'; $tag = preg_match( '/\ssrc=/i', $tag ) ? preg_replace( '/\ssrc=["\'][^"\']*["\']/', ' src="' . esc_url( $thumb ) . '"', $tag ) : '<img src="' . esc_url( $thumb ) . '"' . $m[1] . '>';
			return preg_match( '/\salt=/i', $tag ) ? $tag : preg_replace( '/>$/', ' alt="' . esc_attr( $post->post_title ) . '">', $tag );
		}, $chunk );
	}
	$chunk = str_replace( '{{post.title}}', esc_html( $post->post_title ), $chunk );
	$chunk = str_replace( '{{post.permalink}}', esc_url( get_permalink( $post ) ), $chunk );
	$chunk = str_replace( '{{post.excerpt}}', esc_html( $post->post_excerpt ), $chunk );
	$chunk = str_replace( '{{post.date}}', esc_html( get_the_date( '', $post ) ), $chunk );
	$chunk = str_replace( '{{post.thumbnail_url}}', $thumb ? esc_url( $thumb ) : '', $chunk );
	$chunk = str_replace( '{{post.thumbnail}}', $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $post->post_title ) . '">' : '', $chunk );
	$chunk = preg_replace_callback( '/\{\{post\.meta:([a-z0-9_-]+)\}\}/i', function ( $m ) use ( $post ) {
		$value = get_post_meta( $post->ID, '_emcore_' . $m[1], true );
		return esc_html( is_scalar( $value ) ? (string) $value : '' );
	}, $chunk );
	$tax = $post->post_type . '_cat';
	$slugs = taxonomy_exists( $tax ) ? wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'slugs' ) ) : array();
	if ( is_wp_error( $slugs ) ) { $slugs = array(); }
	return preg_replace( '/<([a-z][a-z0-9-]*)/i', '<$1 data-em-card="1" data-em-cats="' . esc_attr( implode( ' ', $slugs ) ) . '"', $chunk, 1 );
}

function emcore_render_marked_archive_loop( $html, $posts ) {
	if ( stripos( $html, 'data-em-repeat="posts"' ) === false && stripos( $html, "data-em-repeat='posts'" ) === false ) {
		return null;
	}
	$html = emcore_protect_raw_html_blocks( $html, $raw_blocks );
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	if ( ! $dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NODEFDTD ) ) { return null; }
	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//*[@data-em-repeat="posts"]' );
	foreach ( iterator_to_array( $nodes ) as $node ) {
		$parent = $node->parentNode;
		if ( ! $parent ) { continue; }
		$template = $dom->saveHTML( $node );
		foreach ( $posts as $post ) {
			$card = emcore_render_archive_card( $template, $post );
			$card_dom = new DOMDocument();
			$card_dom->loadHTML( '<?xml encoding="utf-8">' . $card, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			$card_node = $card_dom->documentElement;
			if ( $card_node ) { $parent->insertBefore( $dom->importNode( $card_node, true ), $node ); }
		}
		$parent->removeChild( $node );
	}
	$out = $dom->saveHTML();
	$out = preg_replace( '/^<\?xml[^>]*>/', '', $out );
	return emcore_restore_raw_html_blocks( $out, $raw_blocks );
}

function emcore_render_archive_loop( $html, $posts ) {
	$marked = emcore_render_marked_archive_loop( $html, $posts );
	if ( $marked !== null ) {
		$cpt = ! empty( $posts[0] ) ? $posts[0]->post_type : '';
		$bar = $cpt ? emcore_archive_filter_bar( $cpt, $posts ) : '';
		return $bar ? preg_replace( '/<(main|div)([^>]*class=["\'][^"\']*(?:site-main|liver-grid)[^"\']*)/i', $bar . '<$1$2', $marked, 1 ) : $marked;
	}
	if ( ! preg_match( '/\{\{#posts\}\}(.*?)\{\{\/posts\}\}/s', $html, $m ) ) {
		// サンプルカードを1枚の型として実投稿で置き換え
		if ( preg_match( '/<(div|ul|section)([^>]*class=["\'][^"\']*(?:grid|list|cards|archive)[^"\']*["\'][^>]*)>(.*?)<\/\1>/is', $html, $g ) ) {
			if ( preg_match( '/<(a|article|li|div)([^>]*class=["\'][^"\']*(?:card|item|post)[^"\']*["\'][^>]*)>(.*?)<\/\1>/is', $g[3], $card ) ) {
				$tpl = $card[0];
				$out = '';
				foreach ( $posts as $post ) {
					$chunk = $tpl;
					$thumb = get_the_post_thumbnail_url( $post, 'large' );
					$chunk = preg_replace( '/<img([^>]*)src=["\'][^"\']*["\']/', '<img$1src="' . esc_url( $thumb ? $thumb : '' ) . '"', $chunk, 1 );
					$chunk = preg_replace( '/href=["\'][^"\']*["\']/', 'href="' . esc_url( get_permalink( $post ) ) . '"', $chunk, 1 );
					$chunk = preg_replace( '/>[^<]*<\/div>/', '>' . esc_html( $post->post_title ) . '</div>', $chunk, 1 );
					$tax   = $post->post_type . '_cat';
					$slugs = taxonomy_exists( $tax ) ? wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'slugs' ) ) : array();
					if ( is_wp_error( $slugs ) ) { $slugs = array(); }
					$chunk = preg_replace( '/<(a|article|li|div)/i', '<$1 data-em-card="1" data-em-cats="' . esc_attr( implode( ' ', $slugs ) ) . '"', $chunk, 1 );
					$out  .= $chunk;
				}
				$inner = preg_replace( '/<(a|article|li|div)([^>]*class=["\'][^"\']*(?:card|item|post)[^"\']*["\'][^>]*)>(.*?)<\/\1>/is', '', $g[3] );
				$built = '<' . $g[1] . $g[2] . '>' . $out . $inner . '</' . $g[1] . '>';
				$html  = str_replace( $g[0], $built, $html );
			}
		}
		$cpt = ! empty( $posts[0] ) ? $posts[0]->post_type : '';
		if ( $cpt ) {
			$bar = emcore_archive_filter_bar( $cpt, $posts );
			if ( $bar ) {
				$html = preg_replace( '/<(main|div)([^>]*class=["\'][^"\']*(?:site-main|liver-grid)[^"\']*)/i', $bar . '<$1$2', $html, 1 );
			}
		}
		return $html;
	}
	$tpl = $m[1];
	$out = '';
	foreach ( $posts as $post ) {
		$chunk = $tpl;
		$chunk = str_replace( '{{post.title}}', esc_html( $post->post_title ), $chunk );
		$chunk = str_replace( '{{post.permalink}}', esc_url( get_permalink( $post ) ), $chunk );
		$chunk = str_replace( '{{post.excerpt}}', esc_html( $post->post_excerpt ), $chunk );
		$chunk = str_replace( '{{post.date}}', esc_html( get_the_date( '', $post ) ), $chunk );
		$thumb = get_the_post_thumbnail_url( $post, 'large' );
		$chunk = str_replace( '{{post.thumbnail_url}}', $thumb ? esc_url( $thumb ) : '', $chunk );
		$chunk = str_replace(
			'{{post.thumbnail}}',
			$thumb ? '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $post->post_title ) . '">' : '',
			$chunk
		);
		$chunk = preg_replace_callback(
			'/\{\{post\.meta:([a-z0-9_-]+)\}\}/i',
			function ( $mm ) use ( $post ) {
				$k = $mm[1];
				$v = get_post_meta( $post->ID, '_emcore_' . $k, true );
				if ( $v === '' ) {
					$v = get_post_meta( $post->ID, $k, true );
				}
				return esc_html( (string) $v );
			},
			$chunk
		);
		$out .= emcore_render_archive_card( $chunk, $post );
	}
	$html = str_replace( $m[0], $out, $html );
	$cpt  = ! empty( $posts[0] ) ? $posts[0]->post_type : '';
	if ( $cpt && ! preg_match( '/data-em-filter=["\']none["\']/i', $html ) ) {
		$bar = emcore_archive_filter_bar( $cpt, $posts );
		if ( $bar ) {
			$html = preg_replace( '/(<[^>]+data-em-component=["\']post-list["\'][^>]*>)/i', $bar . '$1', $html, 1 );
		}
	}
	return $html;
}
