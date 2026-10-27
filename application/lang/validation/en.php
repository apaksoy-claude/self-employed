<?php
declare(strict_types=1);

return [
    'required' => ':attribute is required.',
    'string' => ':attribute must be text.',
    'email' => ':attribute must be a valid email address.',
    'url' => ':attribute must be a valid web address (starting with http:// or https://).',
    'ip' => ':attribute must be an internet (IP) address such as 203.0.113.7.',
    'numeric' => ':attribute must be a number.',
    'integer' => ':attribute must be a whole number.',
    'alpha_num' => ':attribute may only contain letters, numbers, dashes and underscores.',
    'min' => ':attribute must be at least :min (characters or value).',
    'max' => ':attribute may not be more than :max (characters or value).',
    'between' => ':attribute is outside the allowed range.',
    'in' => ':attribute must be one of: :values.',
    'same' => ':attribute must match :other.',
    'confirmed' => ':attribute confirmation does not match.',
    'regex' => ':attribute has an invalid format.',
    'unique' => ':attribute is already in use.',
    'exists' => ':attribute does not exist.',
];
