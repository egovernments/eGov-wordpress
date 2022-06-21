<?php
/**
 * WPSEO plugin file.
 *
 * @package WPSEO\XML_Sitemaps
 */

/**
 * Renders XML output for sitemaps.
 */
class WPSEO_Sitemaps_Renderer {

	/**
	 * XSL stylesheet for styling a sitemap for web browsers.
	 *
	 * @var string
	 */
	protected $stylesheet = '';

	/**
	 * Holds the get_bloginfo( 'charset' ) value to reuse for performance.
	 *
	 * @var string
	 */
	protected $charset = 'UTF-8';

	/**
	 * Holds charset of output, might be converted.
	 *
	 * @var string
	 */
	protected $output_charset = 'UTF-8';

	/**
	 * If data encoding needs to be converted for output.
	 *
	 * @var bool
	 */
	protected $needs_conversion = false;

	/**
	 * Set up object properties.
	 */
	public function __construct() {
		$stylesheet_url       = preg_replace( '/(^http[s]?:)/', '', $this->get_xsl_url() );
		$this->stylesheet     = '<?xml-stylesheet type="text/xsl" href="' . esc_url( $stylesheet_url ) . '"?>';
		$this->charset        = get_bloginfo( 'charset' );
		$this->output_charset = $this->charset;

		if (
			$this->charset !== 'UTF-8'
			&& function_exists( 'mb_list_encodings' )
			&& in_array( $this->charset, mb_list_encodings(), true )
		) {
			$this->output_charset = 'UTF-8';
		}

		$this->needs_conversion = $this->output_charset !== $this->charset;
	}

	/**
	 * Builds the sitemap index.
	 *
	 * @param array $links Set of sitemaps index links.
	 *
	 * @return string
	 */
	public function get_index( $links ) {

		$xml = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $links as $link ) {
			$xml .= $this->sitemap_index_url( $link );
		}

		/**
		 * Filter to append sitemaps to the index.
		 *
		 * @param string $index String to append to sitemaps index, defaults to empty.
		 */
		$xml .= apply_filters( 'wpseo_sitemap_index', '' );
		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * Builds the sitemap.
	 *
	 * @param array  $links        Set of sitemap links.
	 * @param string $type         Sitemap type.
	 * @param int    $current_page Current sitemap page number.
	 *
	 * @return string
	 */
	public function get_sitemap( $links, $type, $current_page ) {

		$urlset = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" '
			. 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd '
			. 'http://www.google.com/schemas/sitemap-image/1.1 http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd" '
			. 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		/**
		 * Filters the `urlset` for a sitemap by type.
		 *
		 * @api string $urlset The output for the sitemap's `urlset`.
		 */
		$xml = apply_filters( "wpseo_sitemap_{$type}_urlset", $urlset );

		foreach ( $links as $url ) {
			$xml .= $this->sitemap_url( $url );
		}

		/**
		 * Filter to add extra URLs to the XML sitemap by type.
		 *
		 * Only runs for the first page, not on all.
		 *
		 * @param string $content String content to add, defaults to empty.
		 */
		if ( $current_page === 1 ) {
			$xml .= apply_filters( "wpseo_sitemap_{$type}_content", '' );
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Produce final XML output with debug information.
	 *
	 * @param string $sitemap Sitemap XML.
	 *
	 * @return string
	 */
	public function get_output( $sitemap ) {

		$output = '<?xml version="1.0" encoding="' . esc_attr( $this->output_charset ) . '"?>';

		if ( $this->stylesheet ) {
			/**
			 * Filter the stylesheet URL for the XML sitemap.
			 *
			 * @param string $stylesheet Stylesheet URL.
			 */
			$output .= apply_filters( 'wpseo_stylesheet_url', $this->stylesheet ) . "\n";
		}

		$output .= $sitemap;
		$output .= "\n<!-- XML Sitemap generated by Yoast SEO -->";

		return $output;
	}

	/**
	 * Get charset for the output.
	 *
	 * @return string
	 */
	public function get_output_charset() {
		return $this->output_charset;
	}

	/**
	 * Set a custom stylesheet for this sitemap. Set to empty to just remove the default stylesheet.
	 *
	 * @param string $stylesheet Full XML-stylesheet declaration.
	 */
	public function set_stylesheet( $stylesheet ) {
		$this->stylesheet = $stylesheet;
	}

	/**
	 * Build the `<sitemap>` tag for a given URL.
	 *
	 * @param array $url Array of parts that make up this entry.
	 *
	 * @return string
	 */
	protected function sitemap_index_url( $url ) {

		$date = null;

		if ( ! empty( $url['lastmod'] ) ) {
			$date = YoastSEO()->helpers->date->format( $url['lastmod'] );
		}

		$url['loc'] = htmlspecialchars( $url['loc'], ENT_COMPAT, $this->output_charset, false );

		$output  = "\t<sitemap>\n";
		$output .= "\t\t<loc>" . $url['loc'] . "</loc>\n";
		$output .= empty( $date ) ? '' : "\t\t<lastmod>" . htmlspecialchars( $date, ENT_COMPAT, $this->output_charset, false ) . "</lastmod>\n";
		$output .= "\t</sitemap>\n";

		return $output;
	}

	/**
	 * Build the `<url>` tag for a given URL.
	 *
	 * Public access for backwards compatibility reasons.
	 *
	 * @param array $url Array of parts that make up this entry.
	 *
	 * @return string
	 */
	public function sitemap_url( $url ) {

		$date = null;

		if ( ! empty( $url['mod'] ) ) {
			// Create a DateTime object date in the correct timezone.
			$date = YoastSEO()->helpers->date->format( $url['mod'] );
		}

		$output  = "\t<url>\n";
		$output .= "\t\t<loc>" . $this->encode_and_escape( $url['loc'] ) . "</loc>\n";
		$output .= empty( $date ) ? '' : "\t\t<lastmod>" . htmlspecialchars( $date, ENT_COMPAT, $this->output_charset, false ) . "</lastmod>\n";

		if ( empty( $url['images'] ) ) {
			$url['images'] = [];
		}

		foreach ( $url['images'] as $img ) {

			if ( empty( $img['src'] ) ) {
				continue;
			}

			$output .= "\t\t<image:image>\n";
			$output .= "\t\t\t<image:loc>" . $this->encode_and_escape( $img['src'] ) . "</image:loc>\n";
			$output .= "\t\t</image:image>\n";
		}
		unset( $img, $title, $alt );

		$output .= "\t</url>\n";

		/**
		 * Filters the output for the sitemap URL tag.
		 *
		 * @api   string $output The output for the sitemap url tag.
		 *
		 * @param array $url The sitemap URL array on which the output is based.
		 */
		return apply_filters( 'wpseo_sitemap_url', $output, $url );
	}

	/**
	 * Ensure the URL is encoded per RFC3986 and correctly escaped for use in an XML sitemap.
	 *
	 * This method works around a two quirks in esc_url():
	 * 1. `esc_url()` leaves schema-relative URLs alone, while according to the sitemap specs,
	 *    the URL must always begin with a protocol.
	 * 2. `esc_url()` escapes ampersands as `&#038;` instead of the more common `&amp;`.
	 *    According to the specs, `&amp;` should be used, and even though this shouldn't
	 *    really make a difference in practice, to quote Jono: "I'd be nervous about &#038;
	 *    given how many weird and wonderful things eat sitemaps", so better safe than sorry.
	 *
	 * @link https://www.sitemaps.org/protocol.html#xmlTagDefinitions
	 * @link https://www.sitemaps.org/protocol.html#escaping
	 * @link https://developer.wordpress.org/reference/functions/esc_url/
	 *
	 * @param string $url URL to encode and escape.
	 *
	 * @return string
	 */
	protected function encode_and_escape( $url ) {
		$url = $this->encode_url_rfc3986( $url );
		$url = esc_url( $url );
		$url = str_replace( '&#038;', '&amp;', $url );

		if ( strpos( $url, '//' ) === 0 ) {
			// Schema-relative URL for which esc_url() does not add a scheme.
			$url = 'http:' . $url;
		}

		return $url;
	}

	/**
	 * Apply some best effort conversion to comply with RFC3986.
	 *
	 * @param string $url URL to encode.
	 *
	 * @return string
	 */
	protected function encode_url_rfc3986( $url ) {

		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $url;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! empty( $path ) && $path !== '/' ) {
			$encoded_path = explode( '/', $path );

			// First decode the path, to prevent double encoding.
			$encoded_path = array_map( 'rawurldecode', $encoded_path );

			$encoded_path = array_map( 'rawurlencode', $encoded_path );
			$encoded_path = implode( '/', $encoded_path );

			$url = str_replace( $path, $encoded_path, $url );
		}

		$query = wp_parse_url( $url, PHP_URL_QUERY );

		if ( ! empty( $query ) ) {

			parse_str( $query, $parsed_query );

			$parsed_query = http_build_query( $parsed_query, '', '&amp;', PHP_QUERY_RFC3986 );

			$url = str_replace( $query, $parsed_query, $url );
		}

		return $url;
	}

	/**
	 * Retrieves the XSL URL that should be used in the current environment
	 *
	 * When home_url and site_url are not the same, the home_url should be used.
	 * This is because the XSL needs to be served from the same domain, protocol and port
	 * as the XML file that is loading it.
	 *
	 * @return string The XSL URL that needs to be used.
	 */
	protected function get_xsl_url() {
		if ( home_url() !== site_url() ) {
			return home_url( 'main-sitemap.xsl' );
		}

		/*
		 * Fallback to circumvent a cross-domain security problem when the XLS file is
		 * loaded from a different (sub)domain.
		 */
		if ( strpos( plugins_url(), home_url() ) !== 0 ) {
			return home_url( 'main-sitemap.xsl' );
		}

		return plugin_dir_url( WPSEO_FILE ) . 'css/main-sitemap.xsl';
	}
}
