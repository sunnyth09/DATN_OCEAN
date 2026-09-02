<?php

return [
    'encoding'      => 'UTF-8',
    'finalize'      => true,
    'cachePath'     => storage_path('app/purifier'),
    'cacheDefinition' => null,
    'settings'      => [
        'default' => [
            'HTML.Doctype'             => 'HTML 4.01 Transitional',
            'HTML.Allowed'             => 'h1[style|class],h2[style|class],h3[style|class],h4[style|class],h5[style|class],h6[style|class],p[style|class],br,b,i,strong,em,u,strike,sub,sup,a[href|target|title|rel],img[src|alt|width|height|class|style],ul[style|class],ol[style|class],li[style|class],blockquote,pre,code,table,thead,tbody,tr,th,td,span[style|class],div[style|class],iframe[src|width|height|frameborder|allowfullscreen]',
            'CSS.AllowedProperties'    => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align,width,height,margin,margin-left,margin-right',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty'   => false,
        ],
    ],
];
