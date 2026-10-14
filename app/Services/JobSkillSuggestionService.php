<?php

class JobSkillSuggestionService
{
    /**
     * @return string[]
     */
    public static function suggest($jobDescription, $jobTitle = '', $qualifications = '')
    {
        $jd = trim((string) $jobDescription);
        if (mb_strlen($jd) < 40) {
            throw new RuntimeException('Paste a fuller job description in Key Responsibility first (at least a few lines).');
        }

        $openai = new AzureOpenAIService();
        if (!$openai->isConfigured()) {
            throw new RuntimeException('Azure OpenAI is not configured. Set credentials in .env to use AI skill suggestions.');
        }

        $parsed = $openai->extractSkillsFromJobDescription([
            'job_title' => trim((string) $jobTitle),
            'qualifications' => trim((string) $qualifications),
            'job_description' => $jd,
        ]);

        $skills = [];
        if (!empty($parsed['skills']) && is_array($parsed['skills'])) {
            foreach ($parsed['skills'] as $s) {
                if (is_string($s)) {
                    $skills[] = $s;
                }
            }
        }

        if ($skills === []) {
            throw new RuntimeException('AI did not return any skills. Try a more detailed job description.');
        }

        return SkillTagService::ensureInPool($skills);
    }
}
