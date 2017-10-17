<?php
namespace Lille3\PhotoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MainController extends Controller {
    
    public function imageAction(Request $request, $token) {
        /*Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
        $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');
        
        if(in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) {
            $bVerif = false;
        } else {
            $bVerif = true;
        }*/
        
        //$imageUrl = $this->get('lille3_photo.service')->getPath($token, $bVerif);
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


        if ( (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server'))) || 
             (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) )
            return new Response($this->get('lille3_photo.service')->createToken($request, $uid));        
        
        throw new AccessDeniedHttpException();
    }
    
    public function createTokenWithCodeAction(Request $request, $codeapp, $uid) {
        Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
        $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');
        if ( (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server'))) || 
             (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) )
            return new Response($this->get('lille3_photo.service')->createToken($request, $uid, $codeapp));        
        
        throw new AccessDeniedHttpException();
    }
    
    public function createTokenEtuAction(Request $request, $codeetu) {
        Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
        $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

        if ( (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server'))) || 
             (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) )
            return new Response($this->get('lille3_photo.service')->createToken($request, $this->get('lille3_photo.service')->getUidByCodEtu($codeetu)));    

        throw new AccessDeniedHttpException();
    }

    public function createMultiTokensAction(Request $request) {
        Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
        $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

        if ( (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server'))) || 
             (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) ) {            

            $uids = json_decode($request->getContent(), true);
            $tokens = array();
            foreach($uids as $uid) {
                $uid = trim($uid);
                $tokens[$uid] = $this->get('lille3_photo.service')->createToken($request, $uid);
            }
            return new JsonResponse($tokens);
        }
        
        throw new AccessDeniedHttpException();
    }

    public function binaryAction(Request $request, $uid) {
        Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
        $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

        if ( (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server'))) || 
             (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) )
            return $this->imageAction($request, $this->get('lille3_photo.service')->createToken($request, $uid));      
        
        throw new AccessDeniedHttpException();
    }
        
    public function binaryEtuAction(Request $request, $codeetu) {
        Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
        $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

        if ( (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server'))) || 
             (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) )
            return $this->imageAction($request, $this->get('lille3_photo.service')->createToken($request, $this->get('lille3_photo.service')->getUidByCodEtu($codeetu)));

        throw new AccessDeniedHttpException();
    }

    public function createTokenPersAction(Request $request, $codepers) {
        Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
        $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

        if ( (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server'))) || 
             (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) )
            return new Response($this->get('lille3_photo.service')->createToken($request, $this->get('lille3_photo.service')->getUidByCodPers($codepers)));       
        
        throw new AccessDeniedHttpException();
    }

    public function binaryPersAction(Request $request, $codepers) {
        Request::setTrustedProxies(array('127.0.0.1', $request->server->get('REMOTE_ADDR')));
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);
        $request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X_FORWARDED_FOR');

        if ( (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.valid_server'))) || 
             (in_array(gethostbyaddr($request->getClientIp()), $this->getParameter('lille3_photo.xvalid_server'))) )
            return $this->imageAction($request, $this->get('lille3_photo.service')->createToken($request, $this->get('lille3_photo.service')->getUidByCodPers($codepers)));
        
        throw new AccessDeniedHttpException();
    }

    public function uploadAction(Request $request, $uid) {    
        /*$data = array('login' => $refphoto_login, 'password' => $refphoto_password);

        $authenticate_url = $this->get('router')->getRouteCollection()->get('refphoto_authenticate'); 

        $ch = curl_init($authenticate_url);
        curl_setopt($ch, CURLOPT_POST, true);
        $data = http_build_query($data);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);   
        curl_exec($ch);
*/
        return new Response($this->get('lille3_photo.service')->uploadPhoto($request, $uid));       
    }
    
    public function downloadAction(Request $request, $token) {
        $imageUrl = $this->get('lille3_photo.service')->getPathWithoutVerif($token);
        
        $response = new Response();
        if(!empty($imageUrl)) $response->setContent(file_get_contents($imageUrl));
        $response->headers->set('Content-Type', 'image/jpeg');

        return $response;
    }


    public function authenticateAction(Request $request) {
        $refphoto_login = $this->getParameter('general_login');
        $refphoto_password = $this->getParameter('general_password');
        $post_login = $request->request->get('login');
        $post_password = $request->request->get('password');

        if($refphoto_login == $post_login && $refphoto_password == $post_password) {
            return new JsonResponse(true);
        } else {            
            return new JsonResponse(false);
        }
    }
    
}
