<?php

class ElevenLabsService
{
    private $config;

    public function __construct()
    {
        $this->config = config('services')['elevenlabs'];
    }

    public function isConfigured()
    {
        return !empty($this->config['api_key']);
    }

    /**
     * @param string $mediaPath Audio or video file (Scribe v2 accepts both)
     * @param array $options Optional keyterms (array of strings)
     */
    public function transcribe($mediaPath, $options = [])
    {
        if (!$this->isConfigured()) {
            return $this->mockTranscript();
        }

        if (!file_exists($mediaPath)) {
            throw new RuntimeException('Media file not found for transcription: ' . $mediaPath);
        }

        $postFields = [
            'model_id' => $this->config['model'],
            'file' => new CURLFile($mediaPath),
            'diarize' => $this->config['diarize'] ? 'true' : 'false',
            'timestamps_granularity' => 'word',
        ];

        if ($this->config['diarize'] && !empty($this->config['num_speakers'])) {
            $postFields['num_speakers'] = (string) (int) $this->config['num_speakers'];
        }
        if ($this->config['diarize'] && $this->config['detect_speaker_roles']) {
            $postFields['detect_speaker_roles'] = 'true';
        }
        if (!empty($this->config['language_code'])) {
            $postFields['language_code'] = $this->config['language_code'];
        }
        if ($this->config['tag_audio_events']) {
            $postFields['tag_audio_events'] = 'true';
        }

        $keyterms = $this->mergeKeyterms($options);
        if (!empty($keyterms)) {
            $postFields['keyterms'] = json_encode(array_values($keyterms));
        }

        $response = $this->postSpeechToText($postFields);
        $httpCode = $response['code'];
        $body = $response['body'];

        if ($httpCode < 200 || $httpCode >= 300) {
            if ($httpCode === 400 && !empty($keyterms)
                && (stripos($body, 'invalid_keyword') !== false || stripos($body, 'invalid_parameters') !== false)) {
                unset($postFields['keyterms']);
                $response = $this->postSpeechToText($postFields);
                $httpCode = $response['code'];
                $body = $response['body'];
            }
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('ElevenLabs STT failed (HTTP ' . $httpCode . '): ' . $body);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('ElevenLabs STT returned invalid JSON');
        }

        return $this->normalizeResponse($data);
    }

    private function postSpeechToText(array $postFields)
    {
        $ch = curl_init('https://api.elevenlabs.io/v1/speech-to-text');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => ['xi-api-key: ' . $this->config['api_key']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $httpCode, 'body' => $body === false ? '' : $body];
    }

    private function mergeKeyterms($options)
    {
        $terms = [];
        if (!empty($this->config['keyterms'])) {
            foreach (explode(',', $this->config['keyterms']) as $term) {
                $clean = $this->sanitizeKeyterm($term);
                if ($clean !== null) {
                    $terms[] = $clean;
                }
            }
        }
        if (!empty($options['keyterms']) && is_array($options['keyterms'])) {
            foreach ($options['keyterms'] as $term) {
                if (is_array($term)) {
                    $term = isset($term['name']) ? $term['name'] : json_encode($term);
                }
                $clean = $this->sanitizeKeyterm($term);
                if ($clean !== null) {
                    $terms[] = $clean;
                }
            }
        }
        return array_values(array_unique($terms));
    }

    /**
     * ElevenLabs rejects many punctuation/symbol characters in keyterms.
     */
    private function sanitizeKeyterm($term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return null;
        }
        $term = preg_replace('/[^\p{L}\p{N}\s\-\.]/u', ' ', $term);
        $term = preg_replace('/\s+/', ' ', trim($term));
        if ($term === '' || mb_strlen($term) < 2) {
            return null;
        }
        if (mb_strlen($term) > 60) {
            $term = mb_substr($term, 0, 60);
        }
        return $term;
    }

    private function normalizeResponse($data)
    {
        $segments = [];
        $lines = [];

        if (!empty($data['words']) && is_array($data['words'])) {
            $currentSpeaker = null;
            $currentText = '';
            $start = 0;
            $end = 0;

            foreach ($data['words'] as $word) {
                $speaker = isset($word['speaker_id']) ? $word['speaker_id'] : (isset($word['speaker']) ? $word['speaker'] : 'speaker_0');
                $text = isset($word['text']) ? $word['text'] : '';
                $label = $this->mapSpeakerLabel($speaker);

                if ($currentSpeaker !== null && $label !== $currentSpeaker) {
                    $segmentText = trim($currentText);
                    if ($segmentText !== '') {
                        $segments[] = [
                            'speaker' => $currentSpeaker,
                            'text' => $segmentText,
                            'start' => $start,
                            'end' => $end,
                        ];
                        $lines[] = '[' . $currentSpeaker . ']: ' . $segmentText;
                    }
                    $currentText = '';
                }
                if ($currentText === '') {
                    $start = isset($word['start']) ? $word['start'] : 0;
                }
                $currentSpeaker = $label;
                $currentText .= $text . ' ';
                $end = isset($word['end']) ? $word['end'] : $end;
            }

            $segmentText = trim($currentText);
            if ($segmentText !== '' && $currentSpeaker !== null) {
                $segments[] = [
                    'speaker' => $currentSpeaker,
                    'text' => $segmentText,
                    'start' => $start,
                    'end' => $end,
                ];
                $lines[] = '[' . $currentSpeaker . ']: ' . $segmentText;
            }
        }

        $fullText = '';
        if (!empty($lines)) {
            $fullText = implode("\n", $lines);
        } elseif (!empty($data['text'])) {
            $fullText = trim($data['text']);
        } elseif (!empty($data['transcript'])) {
            $fullText = trim($data['transcript']);
        }

        if (empty($segments) && $fullText !== '') {
            $segments[] = ['speaker' => 'Transcript', 'text' => $fullText, 'start' => 0, 'end' => 0];
        }

        return [
            'full_text' => $fullText,
            'raw_full_text' => $fullText,
            'segments' => $segments,
        ];
    }

    private function mapSpeakerLabel($speaker)
    {
        $speaker = strtolower(trim((string) $speaker));
        $map = [
            'agent' => 'Interviewer',
            'customer' => 'Candidate',
            'speaker_0' => 'Interviewer',
            'speaker_1' => 'Candidate',
            'speaker0' => 'Interviewer',
            'speaker1' => 'Candidate',
        ];
        if (isset($map[$speaker])) {
            return $map[$speaker];
        }
        if (strpos($speaker, 'agent') !== false) {
            return 'Interviewer';
        }
        if (strpos($speaker, 'customer') !== false) {
            return 'Candidate';
        }
        return ucfirst($speaker);
    }

    private function mockTranscript()
    {
        return [
            'full_text' => "[Interviewer]: Tell me about your experience.\n[Candidate]: I have several years of relevant experience.",
            'raw_full_text' => "[Interviewer]: Tell me about your experience.\n[Candidate]: I have several years of relevant experience.",
            'segments' => [
                ['speaker' => 'Interviewer', 'text' => 'Tell me about your experience.', 'start' => 0, 'end' => 5],
                ['speaker' => 'Candidate', 'text' => 'I have several years of relevant experience.', 'start' => 5, 'end' => 15],
            ],
        ];
    }
}
