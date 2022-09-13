<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use ResourceLoader;
use OutputPage;
use MediaWiki\MediaWikiServices;

class BodyHooks implements
    \MediaWiki\Hook\BeforePageDisplayHook {

	/**
	 * Injects the inline theme applying script to the document head
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
        global $wgLoadScript,
            $wgScriptPath,
            $wgThemeToggleDefault,
            $wgThemeToggleSiteCssBundled,
            $wgThemeToggleEnableForAnonymousUsers,
            $wgThemeToggleSwitcherStyle,
            $wgThemeToggleAsyncCoreJsDelivery;

        $isAnonymous = $out->getUser()->isAnon();
        if ( !$wgThemeToggleEnableForAnonymousUsers && $isAnonymous ) {
            return;
        }

        $currentTheme = $wgThemeToggleDefault;
        // Retrieve user's preference
        if ( !$isAnonymous ) {
            $currentTheme = MediaWikiServices::getInstance()->getUserOptionsLookup()
                ->getOption( $out->getUser(), PreferenceHooks::getThemePreferenceName(), $wgThemeToggleDefault );
        }

        // Expose configuration variables
        $out->addJsConfigVars( [
            'wgThemeToggleDefault' => $currentTheme,
            'wgThemeToggleSiteCssBundled' => $wgThemeToggleSiteCssBundled
        ] );
        if ( !$isAnonymous && ExtensionConfig::getPreferenceGroupName() !== null ) {
            $out->addJsConfigVars( [
                'wgThemeTogglePrefGroup' => ExtensionConfig::getPreferenceGroupName()
            ] );
        }

        // Inject the theme applying script into <head> to reduce latency
        $rlEndpoint = self::getThemeLoadEndpointUri( $out );
        $rlEndpointJson = json_encode( $rlEndpoint, JSON_UNESCAPED_SLASHES );
        if ( ExtensionConfig::useAsyncJsDelivery() ) {
    		self::injectScriptTag( $out, 'ext.themes.loadEndpointVar', "THEMELOAD=$rlEndpointJson" );
    		self::injectScriptTag( $out, 'ext.themes.apply', '', "async src=\"$rlEndpoint&modules=ext.themes.apply"
                . '&only=scripts&raw=1"');
        } else {
    		self::injectScriptTag( $out, 'ext.themes.apply', sprintf(
                '(function(){var THEMELOAD=%s;%s})()',
                $rlEndpointJson,
                ModuleHelper::getCoreJsToInject()
            ) );
        }

        // Inject the theme switcher as a ResourceLoader module
        if ( ModuleHelper::getSwitcherModuleId() !== null ) {
            $out->addModules( [ 'ext.themes.switcher' ] );
        }
	}

    private static function injectScriptTag( OutputPage $outputPage, string $id, string $script, $attributes = false ) {
		$nonce = $outputPage->getCSP()->getNonce();
    	$outputPage->addHeadItem( $id, sprintf(
	    	'<script%s%s>%s</script>',
		    $nonce !== false ? " nonce=\"$nonce\"" : '',
            $attributes !== false ? " $attributes" : '',
            $script
        ) );
    }

    private static function getThemeLoadEndpointUri( OutputPage $outputPage ): string {
        $out = ExtensionConfig::getLoadScript() . '?lang=' . $outputPage->getLanguage()->getCode();
        if ( ResourceLoader::inDebugMode() ) {
            $rlEndpoint .= '&debug=1';
        }
        return $out;
    }
}