<?php

namespace Members\Controller\Plugin;

use Pimcore\Model\Document\Page;
use Members\Tool\Observer;
use Members\Model\Configuration;

class Frontend extends \Zend_Controller_Plugin_Abstract
{
    private static $renderer = NULL;

    public function preDispatch(\Zend_Controller_Request_Abstract $request)
    {
        parent::preDispatch($request);

        self::$renderer = \Zend_Controller_Action_HelperBroker::getExistingHelper('ViewRenderer');
        self::$renderer->initView();

        $view = self::$renderer->view;
        $view->addHelperPath(PIMCORE_PLUGINS_PATH . '/Members/lib/Members/View/Helper', 'Members\View\Helper');

    }

    public function postDispatch(\Zend_Controller_Request_Abstract $request)
    {
        parent::postDispatch($request);

        if ($request->getParam('document') instanceof Page)
        {
            $document = $request->getParam('document');

            $groups = Observer::getDocumentRestrictedGroups( $document );
            self::$renderer->view->headMeta()->appendName('m:groups', implode(',', $groups), array());

            $this->handleDocumentAuthentication($request->getParam('document'));
        }

    }

    /**
     * @param Page $document
     *
     * @return bool
     */
    private function handleDocumentAuthentication($document)
    {
        //@fixme! bad?
        if (isset($_COOKIE['pimcore_admin_sid']))
        {
            return FALSE;
        }

        //@fixme: does not work in backend? :)
        if( !\Pimcore\Tool::isFrontend() )
        {
            return FALSE;
        }

        //now load restriction and redirect user to login page, if page is restricted!
        $restrictedType = Observer::isRestrictedDocument( $document );

        if( $restrictedType['section'] == Observer::SECTION_ALLOWED )
        {
            return FALSE;
        }

        if(  $restrictedType['state'] == Observer::STATE_LOGGED_IN && $restrictedType['section'] == Observer::SECTION_ALLOWED )
        {
            return FALSE;
        }

        //do not check /members pages, they will check them itself.
        $requestUrl = $this->getRequest()->getRequestUri();
        $nowAllowed = array(
            Configuration::getLocalizedPath('routes.login'),
            Configuration::getLocalizedPath('routes.profile')
        );

        foreach( $nowAllowed as $not)
        {
            if( substr($requestUrl, 0, strlen($not)) == $not)
            {
                return FALSE;
            }
        }

        if( in_array($this->getRequest()->getRequestUri(), $nowAllowed) )
        {
            return FALSE;
        }

        if( $restrictedType['state'] === Observer::STATE_LOGGED_IN && $restrictedType['section'] === Observer::SECTION_NOT_ALLOWED)
        {
            $url = Configuration::getLocalizedPath('routes.profile');
        }
        else
        {
            $url = sprintf('%s?back=%s',
                Configuration::getLocalizedPath('routes.login'),
                urlencode( $this->getRequest()->getRequestUri() )
            );
        }

        $response = $this->getResponse();
        $response->setHeader('Location', $url, true);
        $response->sendHeaders();
        exit;

    }
}