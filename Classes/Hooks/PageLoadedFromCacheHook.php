<?php
namespace Qbus\NginxCache\Hooks;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PageLoadedFromCacheHook
 *
 * @author Benjamin Franzke <bfr@qbus.de>
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class PageLoadedFromCacheHook
{
    /**
     * @return void
     */
    public function loadedFromCache(&$params)
    {
        $tsfe = $params['pObj'];
        $row = $params['cache_pages_row'];

        if (!isset($row['tx_nginx_cache_tags'])) {
            return;
        }

        /* We populate config into $TSFE as we do needed it for the isAdminPanelVisisble check.
         * $TSFE does the same after this hook (pageLoadedFromCache). So it should be safe.  */
        $tsfe->config = $row['cache_data'];

        $uri = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');

        $cachable = (
            $tsfe->isStaticCacheble() &&
            $tsfe->doWorkspacePreview() === false &&
            strpos($uri, '?') === false &&
            $this->isAdminPanelVisible($tsfe) === false &&
            $this->isFrontendEditingActive() === false &&
            $this->getEnvironmentService()->getServerRequestMethod() === 'GET'
        );

        if (!$cachable) {
            return;
        }

        $lifetime = $row['expires'] - $GLOBALS['EXEC_TIME'];
        $tags = isset($row['tx_nginx_cache_tags']) ? $row['tx_nginx_cache_tags'] : [];
        $this->getCacheManager()->getCache('nginx_cache')->set(md5($uri), $uri, $tags, $lifetime);
    }

    /**
     * @return bool
     */
    protected function isAdminPanelVisible($tsfe)
    {
        return (
            $tsfe->isBackendUserLoggedIn() &&
            $GLOBALS['BE_USER'] instanceof \TYPO3\CMS\Backend\FrontendBackendUserAuthentication &&
            $GLOBALS['BE_USER']->isAdminPanelVisible()
        );
    }

    /**
     * @return bool
     */
    protected function isFrontendEditingActive()
    {
        return (
            /* Note: we do not use $GLOBALS['BE_USER']->isFrontendEditingActive() as that checks
             * additional for adminPanel->isAdminModuleEnabled('edit'), but that has no influence
             * on cache clearing. */
            $GLOBALS['TSFE']->displayEditIcons == 1 ||
            $GLOBALS['TSFE']->displayFieldEditIcons == 1
        );
    }

    /**
     * @return \TYPO3\CMS\Core\Cache\CacheManager;
     */
    protected function getCacheManager()
    {
        return GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
    }

    /**
     * @return \TYPO3\CMS\Extbase\Service\EnvironmentService
     */
    protected function getEnvironmentService()
    {
        return GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Service\EnvironmentService::class);
    }
}
