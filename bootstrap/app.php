<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        
        // La sección 'api' es donde se aplican los middlewares a tus rutas API (routes/api.php).
        $middleware->api(prepend: [
            
            // 💡 LÍNEA CLAVE AÑADIDA: Esto activa la funcionalidad CORS nativa.
            //    Leerá las reglas que pusiste en config/cors.php
            \Illuminate\Http\Middleware\HandleCors::class, 
            
            // Middleware de Sanctum (ya estaba)
            
            //\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Las rutas que coincidan con estos patrones no requerirán un token CSRF
        $middleware->validateCsrfTokens(except: [
            'http://localhost:8000/*',
            'https://pimos2-production-8705.up.railway.app/*',

            // 🔥 LÍNEA CRUCIAL: Excluye la ruta del Login.
            //'api/login',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

    })->create();