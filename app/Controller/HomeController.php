<?php

declare(strict_types=1);

namespace App\Controller;

use Framework\Routing\Attribute\Route;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Attribute\Get;

#[Route('/')]  
class HomeController
{
   #[Get('/')]
    public function index(Request $request): Response
    { 
        return Response::json([
            'status' => 200,
            'message' => 'Success'
        ]); 
    }



    #[Get('/secure')]
    public function secureData(Request $request): array
    {
        return ['status' => 'Success', 'data' => 'Pasok ka sa auto-discovered multi-middleware path!'];
    }

    #[Get('/dashboard')]
    public function dashboard(Request $request): string
    {
        return "Welcome to your protected dashboard!";
    }
   
    #[Get('/public')]
    public function publicData(Request $request): array
    {
        // This uses the new $request->headers() API method from scratch
        return $request->headers();
    }
        #[Get('/crash-test')]
    public function crash(Request $request): string
    {
        // Sasadyain nating tawagin ang variable na wala naman para mag-trigger ng internal error
        return $aksidentengMalingVariable; 
    }
           #[Get('/publics')]
    public function publicDatsa(Request $request): array
    {
        return [
            // Safe and optimized reading directly out of the $_ENV memory table
            'app_name'     => $_ENV['APP_NAME'] ?? 'Default Name',
            'environment'  => $_ENV['APP_ENV'] ?? 'production',
            'debug_mode'   => $_ENV['APP_DEBUG'] ?? 'false',
            
            // 🛠️ FIXED: Changed from SECRET_KEY to APP_KEY to match your actual .env file key name
            'secret_token' => $_ENV['APP_KEY'] ?? 'No Token Found' 
        ];
    }



}