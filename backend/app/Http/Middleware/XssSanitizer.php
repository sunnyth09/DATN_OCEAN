<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class XssSanitizer
{
    public function handle(Request $request, Closure $next)
    {
        $input = $request->all();
        
        array_walk_recursive($input, function(&$input, $key) {
            // Bỏ qua sanitize cho các trường HTML hợp lệ (từ WYSIWYG)
            $skipHtmlFields = ['content', 'description', 'body', 'html_content'];
            
            if (is_string($input) && !in_array($key, $skipHtmlFields)) {
                $input = strip_tags($input);
            }
        });

        $request->merge($input);
        return $next($request);
    }
}
