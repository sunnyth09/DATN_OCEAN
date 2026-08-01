<?php

namespace App\Helpers;

class ProfanityFilter
{
    /**
     * Lọc các từ ngữ thô tục trong chuỗi
     *
     * @param string|null $text
     * @return string|null
     */
    public static function filter(?string $text): ?string
    {
        if (empty($text)) {
            return $text;
        }

        $badWords = [
            'c[uứ]t', 'đ[ịi]t', 'đ[ụuù]', 'l[ồoòóõọôốồổỗộơớờởỡợỉiìíĩị]n', 'l[oòóỏõọ]z', 'lol', 'c[ặa]c', 'c[aăâ]k', 'c[eẹ]c', 'bu[ồoò]i', 
            'đ[ĩiỉ]', 'đi[ếe]m', 'ch[óo]\s+đ[ẻe]', 'vcl', 'vl', 'v[ãa]i\s+l[ồoòóõọôốồổỗộơớờởỡợỉiìíĩị]n',
            'đm', 'đkm', 'đ[ịi]t\s+m[ẹe]', 'đ[éềe]o', 'th[ằa]ng\s+ch[óo]', 'con\s+ch[óo]', 'dcm', 'vcc',
            'fuck', 'shit', 'bitch', 'asshole', 'dick', 'pussy', 'cunt', 'slut', 'whore'
        ];

        // Sử dụng lookaround cho Unicode letters thay vì \b để hỗ trợ tốt hơn cho tiếng Việt
        $pattern = '/(?<!\p{L})(' . implode('|', $badWords) . ')(?!\p{L})/iu';
        
        return preg_replace($pattern, '***', $text);
    }

    /**
     * Kiểm tra xem chuỗi có chứa từ ngữ thô tục hay không
     */
    public static function hasProfanity(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        $badWords = [
            'c[uứ]t', 'đ[ịi]t', 'đ[ụuù]', 'l[ồoòóõọôốồổỗộơớờởỡợỉiìíĩị]n', 'l[oòóỏõọ]z', 'lol', 'c[ặa]c', 'c[aăâ]k', 'c[eẹ]c', 'bu[ồoò]i', 
            'đ[ĩiỉ]', 'đi[ếe]m', 'ch[óo]\s+đ[ẻe]', 'vcl', 'vl', 'v[ãa]i\s+l[ồoòóõọôốồổỗộơớờởỡợỉiìíĩị]n',
            'đm', 'đkm', 'đ[ịi]t\s+m[ẹe]', 'đ[éềe]o', 'th[ằa]ng\s+ch[óo]', 'con\s+ch[óo]', 'dcm', 'vcc',
            'fuck', 'shit', 'bitch', 'asshole', 'dick', 'pussy', 'cunt', 'slut', 'whore'
        ];

        $pattern = '/(?<!\p{L})(' . implode('|', $badWords) . ')(?!\p{L})/iu';
        
        return preg_match($pattern, $text) === 1;
    }
}
