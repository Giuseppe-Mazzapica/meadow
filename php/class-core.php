<?php

namespace Rarst\Meadow;

/**
 * Main plugin class.
 */
class Core extends \Pimple {

	/**
	 * @param array $values
	 */
	public function __construct( $values = array() ) {

		$defaults['twig.options']     = array();
		$defaults['twig.directories'] = array();

		$defaults['twig.loader'] = function ( $meadow ) {

			// this needs to be lazy or theme switchers and alike explode it

			$stylesheet_dir  = get_stylesheet_directory();
			$template_dir    = get_template_directory();
			$calculated_dirs = array(
				$stylesheet_dir,
				$template_dir,
				plugin_dir_path( __DIR__ ) . 'twig',
			);

			// enables explicit inheritance from parent theme in child
			if ( $stylesheet_dir !== $template_dir ) {
				$calculated_dirs[] = dirname( $template_dir );
			}

			$directories = array_unique(
				array_merge(
					$calculated_dirs,
					$meadow['twig.directories']
				)
			);

			return new \Twig_Loader_Filesystem( $directories );
		};

		$defaults['twig.undefined_function'] = array( __CLASS__, 'undefined_function' );
		$defaults['twig.undefined_filter']   = array( __CLASS__, 'undefined_filter' );

		$defaults['twig.environment'] = function ( $meadow ) {
			$environment      = new \Twig_Environment( $meadow['twig.loader'], $meadow['twig.options'] );
			$meadow_extension = new Extension();
			$environment->addExtension( $meadow_extension );
			$environment->registerUndefinedFunctionCallback( $meadow['twig.undefined_function'] );
			$environment->registerUndefinedFilterCallback( $meadow['twig.undefined_filter'] );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$debug_extension = new \Twig_Extension_Debug();
				$environment->addExtension( $debug_extension );
				$environment->enableDebug();
			}

			return $environment;
		};

		$defaults['hierarchy'] = function () {
			return new Template_Hierarchy();
		};

		parent::__construct( array_merge( $defaults, $values ) );
	}

	/**
	 * Handler for undefined functions in Twig to pass them through to PHP and buffer echoing versions.
	 *
	 * @param string $function_name
	 *
	 * @return bool|\Twig_SimpleFunction
	 */
	static function undefined_function( $function_name ) {

		if ( function_exists( $function_name ) ) {
			return new \Twig_SimpleFunction(
				$function_name,
				function () use ( $function_name ) {

					ob_start();
					$return = call_user_func_array( $function_name, func_get_args() );
					$echo   = ob_get_clean();

					return empty( $echo ) ? $return : $echo;
				},
				array( 'is_safe' => array( 'all' ) )
			);
		}

		return false;
	}

	/**
	 * Handler for fallback to WordPress filters for undefined Twig filters in template.
	 *
	 * @param string $filter_name
	 *
	 * @return bool|\Twig_SimpleFilter
	 */
	static function undefined_filter( $filter_name ) {

		return new \Twig_SimpleFilter(
			$filter_name,
			function () use ( $filter_name ) {

				return apply_filters( $filter_name, func_get_arg( 0 ) );
			},
			array( 'is_safe' => array( 'all' ) )
		);
	}

	public function enable() {

		/** @var Template_Hierarchy $hierarchy */
		$hierarchy = $this['hierarchy'];
		$hierarchy->enable();
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_filter( 'get_search_form', array( $this, 'get_search_form' ), 9 );
	}

	public function disable() {

		/** @var Template_Hierarchy $hierarchy */
		$hierarchy = $this['hierarchy'];
		$hierarchy->disable();
		remove_filter( 'template_include', array( $this, 'template_include' ) );
		remove_filter( 'get_search_form', array( $this, 'get_search_form' ), 9 );
	}

	/**
	 * @param string $template
	 *
	 * @return string|bool
	 */
	public function template_include( $template ) {

		if ( '.twig' === substr( $template, - 5 ) ) {
			/** @var \Twig_Environment $twig */
			$twig = $this['twig.environment'];

			// TODO context API
			echo $twig->render( basename( $template ), array() );

			return false;
		}

		return $template;
	}

	/**
	 * @param string $form
	 *
	 * @return string
	 */
	public function get_search_form( $form ) {

		// because first time it's action
		if ( ! empty( $form ) ) {
			/** @var \Twig_Environment $twig */
			$twig = $this['twig.environment'];

			return $twig->render( 'searchform.twig', array() );
		}

		return $form;
	}
}