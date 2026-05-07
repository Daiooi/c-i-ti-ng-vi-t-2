<?php

return [
    'app_name' => 'He thong quan ly sinh vien thong minh',
    'base_url' => '',
    'db_path' => dirname(__DIR__) . '/storage/student_manager.sqlite',
    'session_path' => dirname(__DIR__) . '/storage/sessions',
    'ai_provider' => getenv('AI_PROVIDER') ?: 'ollama',
    'ollama_base_url' => getenv('OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434',
    'ollama_model' => getenv('OLLAMA_MODEL') ?: 'qwen2.5:1.5b',
    'ollama_num_ctx' => (int) (getenv('OLLAMA_NUM_CTX') ?: 768),
    'ollama_num_predict' => (int) (getenv('OLLAMA_NUM_PREDICT') ?: 120),
    'ollama_keep_alive' => getenv('OLLAMA_KEEP_ALIVE') ?: '0',
    'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
    'gemini_model' => getenv('GEMINI_MODEL') ?: 'gemini-2.0-flash',
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'openai_model' => getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini',
    'session_name' => 'smart_student_manager',
];
