<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 * title="API Documentation",
 * version="1.0.0"
 * )
 * @OA\SecurityScheme(
 * securityScheme="bearer_token",
 * type="http", 
 * scheme="bearer",
 * bearerFormat="JWT",
 * in="header",
 * name="Authorization",
 * description="Ingrese el token de autenticación con el prefijo 'Bearer '. Ejemplo: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
 * )
 * @OA\Server(url="http://pimos2-production-8705.up.railway.app", description="Servidor de Producción")
 *
 * @OA\Security(
 * security={{"bearer_token":{}}}
 * )
 * * @OA\Tag(
 * name="Autenticación",
 * description="Operaciones de inicio de sesión y gestión de tokens"
 * )
 */

abstract class Controller
{
    //
}