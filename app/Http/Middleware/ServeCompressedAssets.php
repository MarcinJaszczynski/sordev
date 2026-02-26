<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServeCompressedAssets
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Sprawdź czy to request o zasób statyczny
        if (!$this->isStaticAsset($request)) {
            return $response;
        }
        
        // Sprawdź czy klient obsługuje kompresję
        $acceptEncoding = $request->header('Accept-Encoding', '');
        
        if (str_contains($acceptEncoding, 'br')) {
            return $this->tryServeCompressed($request, $response, '.br', 'br');
        }
        
        if (str_contains($acceptEncoding, 'gzip')) {
            return $this->tryServeCompressed($request, $response, '.gz', 'gzip');
        }
        
        return $response;
    }
    
    private function isStaticAsset(Request $request): bool
    {
        $path = $request->path();
        
        return str_starts_with($path, 'build/') || 
               str_ends_with($path, '.js') || 
               str_ends_with($path, '.css') ||
               str_ends_with($path, '.svg');
    }
    
    private function tryServeCompressed(Request $request, Response $response, string $extension, string $encoding): Response
    {
        $path = public_path($request->path());
        $compressedPath = $path . $extension;
        
        if (file_exists($compressedPath)) {
            $content = file_get_contents($compressedPath);
            
            return response($content)
                ->header('Content-Encoding', $encoding)
                ->header('Content-Type', $response->headers->get('Content-Type'))
                ->header('Content-Length', strlen($content))
                ->header('Cache-Control', 'public, max-age=31536000')
                ->header('Vary', 'Accept-Encoding');
        }
        
        return $response;
    }
}
