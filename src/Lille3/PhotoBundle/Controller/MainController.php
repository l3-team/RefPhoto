<?php
namespace Lille3\PhotoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MainController extends Controller {
    
	public function imageAction($token) {
            
		$imageUrl = $this->get('lille3_photo.service')->getPath($token);
		$response = new Response();
		if(!empty($imageUrl)) $response->setContent(file_get_contents($imageUrl));
		$response->headers->set('Content-Type', 'image/jpeg');

		return $response;
	}

	public function createTokenAction(Request $request, $uid) {
            
		Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
		Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
		$request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');
                
                
		if(!in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server')))
			throw new AccessDeniedHttpException();

		return new Response($this->get('lille3_photo.service')->createToken($uid));
	}
        
        public function createTokenEtuAction(Request $request, $codeetu) {
		Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
                Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
                $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

		if(!in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server')))
			throw new AccessDeniedHttpException();

		return new Response($this->get('lille3_photo.service')->createToken($this->get('lille3_photo.service')->getUidByCodEtu($codeetu)));
	}

	public function createMultiTokensAction(Request $request) {
		Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
                Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
                $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

		if(!in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server')))
			throw new AccessDeniedHttpException();

		$uids = json_decode($request->getContent(), true);
		$tokens = array();
		foreach($uids as $uid) {
			$uid = trim($uid);
			$tokens[$uid] = $this->get('lille3_photo.service')->createToken($uid);
		}
		return new JsonResponse($tokens);
	}

	public function binaryAction(Request $request, $uid) {
		Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
                Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
                $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

		if(!in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server')))
                        throw new AccessDeniedHttpException();

		return $this->imageAction($this->get('lille3_photo.service')->createToken($uid));
	}
        
        public function binaryEtuAction(Request $request, $codeetu) {
		Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
                Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
                $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

		if(!in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server')))
                        throw new AccessDeniedHttpException();
                
		return $this->imageAction($this->get('lille3_photo.service')->createToken($this->get('lille3_photo.service')->getUidByCodEtu($codeetu)));
	}

	public function createTokenPersAction(Request $request, $codepers) {
		Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
                Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
                $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

		if(!in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server')))
			throw new AccessDeniedHttpException();

		return new Response($this->get('lille3_photo.service')->createToken($this->get('lille3_photo.service')->getUidByCodPers($codepers)));
	}

	public function binaryPersAction(Request $request, $codepers) {
		Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
                Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
                $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

		if(!in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server')))
                        throw new AccessDeniedHttpException();
                
		return $this->imageAction($this->get('lille3_photo.service')->createToken($this->get('lille3_photo.service')->getUidByCodPers($codepers)));
	}

}
