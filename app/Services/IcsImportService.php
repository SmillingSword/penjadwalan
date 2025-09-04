<?php

namespace App\Services;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Eluceo\iCal\Domain\Entity\Calendar as ICalCalendar;
use Eluceo\iCal\Domain\Entity\Event as ICalEvent;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IcsImportService
{
    /**
     * Import ICS file content into a calendar.
     */
    public function importIcsFile(string $icsContent, Calendar $targetCalendar, array $options = []): array
    {
        $options = array_merge([
            'overwrite_existing' => false,
            'skip_duplicates' => true,
            'timezone' => 'UTC',
            'import_private_events' => true,
            'max_events' => 1000,
        ], $options);

        try {
            // Parse the ICS content
            $parsedEvents = $this->parseIcsContent($icsContent);
            
            $importResults = [
                'total_events_found' => count($parsedEvents),
                'imported_events' => 0,
                'skipped_events' => 0,
                'failed_events' => 0,
                'errors' => [],
                'imported_event_ids' => [],
            ];

            // Limit the number of events to process
            if (count($parsedEvents) > $options['max_events']) {
                $parsedEvents = array_slice($parsedEvents, 0, $options['max_events']);
                $importResults['errors'][] = "Limited import to {$options['max_events']} events due to size constraints.";
            }

            DB::beginTransaction();

            foreach ($parsedEvents as $index => $eventData) {
                try {
                    $result = $this->importSingleEvent($eventData, $targetCalendar, $options);
                    
                    if ($result['status'] === 'imported') {
                        $importResults['imported_events']++;
                        $importResults['imported_event_ids'][] = $result['event_id'];
                    } elseif ($result['status'] === 'skipped') {
                        $importResults['skipped_events']++;
                    }
                    
                    if (isset($result['error'])) {
                        $importResults['errors'][] = "Event {$index}: " . $result['error'];
                    }
                } catch (\Exception $e) {
                    $importResults['failed_events']++;
                    $importResults['errors'][] = "Event {$index}: " . $e->getMessage();
                    Log::error('ICS Import Error', [
                        'event_index' => $index,
                        'error' => $e->getMessage(),
                        'calendar_id' => $targetCalendar->id,
                    ]);
                }
            }

            DB::commit();

            return $importResults;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Failed to import ICS file: ' . $e->getMessage());
        }
    }

    /**
     * Import from a URL (e.g., Google Calendar, Outlook).
     */
    public function importFromUrl(string $url, Calendar $targetCalendar, array $options = []): array
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Laravel Calendar App/1.0',
                ]
            ]);

            $icsContent = file_get_contents($url, false, $context);
            
            if ($icsContent === false) {
                throw new \Exception('Failed to fetch ICS content from URL');
            }

            return $this->importIcsFile($icsContent, $targetCalendar, $options);
        } catch (\Exception $e) {
            throw new \Exception('Failed to import from URL: ' . $e->getMessage());
        }
    }

    /**
     * Validate ICS file content.
     */
    public function validateIcsContent(string $icsContent): array
    {
        try {
            $parsedEvents = $this->parseIcsContent($icsContent);
            
            $validation = [
                'valid' => true,
                'events_count' => count($parsedEvents),
                'warnings' => [],
                'errors' => [],
            ];

            // Check for common issues
            foreach ($parsedEvents as $index => $eventData) {
                if (empty($eventData['title'])) {
                    $validation['warnings'][] = "Event {$index}: Missing title";
                }
                
                if (empty($eventData['start_at'])) {
                    $validation['errors'][] = "Event {$index}: Missing start date";
                    $validation['valid'] = false;
                }
                
                if (!empty($eventData['end_at']) && $eventData['start_at'] >= $eventData['end_at']) {
                    $validation['warnings'][] = "Event {$index}: End date is not after start date";
                }
            }

            return $validation;
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'events_count' => 0,
                'warnings' => [],
                'errors' => ['Failed to parse ICS content: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Get preview of events from ICS content.
     */
    public function previewIcsContent(string $icsContent, int $limit = 10): array
    {
        try {
            $parsedEvents = $this->parseIcsContent($icsContent);
            $preview = array_slice($parsedEvents, 0, $limit);
            
            return [
                'total_events' => count($parsedEvents),
                'preview_events' => $preview,
                'showing_count' => count($preview),
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to preview ICS content: ' . $e->getMessage());
        }
    }

    /**
     * Parse ICS content and extract event data.
     */
    private function parseIcsContent(string $icsContent): array
    {
        $events = [];
        
        // Clean up the ICS content
        $icsContent = $this->cleanIcsContent($icsContent);
        
        // Split into lines
        $lines = explode("\n", $icsContent);
        $currentEvent = null;
        $inEvent = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $currentEvent = [
                    'title' => '',
                    'description' => '',
                    'location' => '',
                    'start_at' => null,
                    'end_at' => null,
                    'all_day' => false,
                    'timezone' => 'UTC',
                    'rrule' => null,
                    'uid' => null,
                    'status' => 'confirmed',
                    'is_private' => false,
                ];
                continue;
            }
            
            if ($line === 'END:VEVENT' && $inEvent) {
                if ($currentEvent && $currentEvent['start_at']) {
                    $events[] = $currentEvent;
                }
                $currentEvent = null;
                $inEvent = false;
                continue;
            }
            
            if (!$inEvent || !$currentEvent) {
                continue;
            }
            
            // Parse event properties
            $this->parseEventProperty($line, $currentEvent);
        }
        
        return $events;
    }

    /**
     * Parse a single event property line.
     */
    private function parseEventProperty(string $line, array &$eventData): void
    {
        // Handle line continuations (lines starting with space or tab)
        if (preg_match('/^[\s\t]/', $line)) {
            return; // Skip for now, would need to handle multi-line properties
        }
        
        $colonPos = strpos($line, ':');
        if ($colonPos === false) {
            return;
        }
        
        $property = substr($line, 0, $colonPos);
        $value = substr($line, $colonPos + 1);
        
        // Handle parameters (e.g., DTSTART;TZID=America/New_York:20240101T100000)
        $parameters = [];
        if (strpos($property, ';') !== false) {
            $parts = explode(';', $property);
            $property = $parts[0];
            
            for ($i = 1; $i < count($parts); $i++) {
                $paramParts = explode('=', $parts[$i], 2);
                if (count($paramParts) === 2) {
                    $parameters[$paramParts[0]] = $paramParts[1];
                }
            }
        }
        
        switch ($property) {
            case 'SUMMARY':
                $eventData['title'] = $this->unescapeIcsValue($value);
                break;
                
            case 'DESCRIPTION':
                $eventData['description'] = $this->unescapeIcsValue($value);
                break;
                
            case 'LOCATION':
                $eventData['location'] = $this->unescapeIcsValue($value);
                break;
                
            case 'DTSTART':
                $eventData['start_at'] = $this->parseIcsDateTime($value, $parameters);
                if (strlen($value) === 8) { // YYYYMMDD format (all-day)
                    $eventData['all_day'] = true;
                }
                break;
                
            case 'DTEND':
                $eventData['end_at'] = $this->parseIcsDateTime($value, $parameters);
                break;
                
            case 'RRULE':
                $eventData['rrule'] = $value;
                break;
                
            case 'UID':
                $eventData['uid'] = $value;
                break;
                
            case 'STATUS':
                $eventData['status'] = strtolower($value);
                break;
                
            case 'CLASS':
                $eventData['is_private'] = strtolower($value) === 'private';
                break;
                
            case 'TZID':
                $eventData['timezone'] = $value;
                break;
        }
    }

    /**
     * Parse ICS datetime value.
     */
    private function parseIcsDateTime(string $value, array $parameters = []): ?Carbon
    {
        try {
            // Handle timezone
            $timezone = $parameters['TZID'] ?? 'UTC';
            
            // Handle different datetime formats
            if (strlen($value) === 8) {
                // YYYYMMDD format (all-day event)
                return Carbon::createFromFormat('Ymd', $value, $timezone)->startOfDay();
            } elseif (strlen($value) === 15 && substr($value, -1) === 'Z') {
                // YYYYMMDDTHHMMSSZ format (UTC)
                return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC');
            } elseif (strlen($value) === 15) {
                // YYYYMMDDTHHMMSS format
                return Carbon::createFromFormat('Ymd\THis', $value, $timezone);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to parse ICS datetime', [
                'value' => $value,
                'parameters' => $parameters,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Import a single event.
     */
    private function importSingleEvent(array $eventData, Calendar $targetCalendar, array $options): array
    {
        // Check for existing event by UID
        if (!empty($eventData['uid']) && $options['skip_duplicates']) {
            $existingEvent = Event::where('calendar_id', $targetCalendar->id)
                ->where('external_id', $eventData['uid'])
                ->first();
                
            if ($existingEvent && !$options['overwrite_existing']) {
                return ['status' => 'skipped', 'reason' => 'Duplicate UID'];
            }
        }

        // Skip private events if not importing them
        if ($eventData['is_private'] && !$options['import_private_events']) {
            return ['status' => 'skipped', 'reason' => 'Private event'];
        }

        try {
            $event = Event::create([
                'calendar_id' => $targetCalendar->id,
                'title' => $eventData['title'] ?: 'Imported Event',
                'description_md' => $eventData['description'],
                'location' => $eventData['location'],
                'start_at' => $eventData['start_at']->utc(),
                'end_at' => $eventData['end_at'] ? $eventData['end_at']->utc() : $eventData['start_at']->clone()->addHour()->utc(),
                'all_day' => $eventData['all_day'],
                'timezone' => $eventData['timezone'],
                'rrule' => $eventData['rrule'],
                'is_private' => $eventData['is_private'],
                'external_id' => $eventData['uid'],
                'external_source' => 'ics_import',
            ]);

            return [
                'status' => 'imported',
                'event_id' => $event->id,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clean ICS content.
     */
    private function cleanIcsContent(string $content): string
    {
        // Remove BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        // Unfold lines (handle line continuations)
        $content = preg_replace("/\n[\s\t]/", "", $content);
        
        return $content;
    }

    /**
     * Unescape ICS value.
     */
    private function unescapeIcsValue(string $value): string
    {
        $value = str_replace(['\\n', '\\N'], "\n", $value);
        $value = str_replace(['\\,', '\\;', '\\\\'], [',', ';', '\\'], $value);
        return $value;
    }

    /**
     * Get import statistics for a calendar.
     */
    public function getImportStatistics(Calendar $calendar): array
    {
        $importedEvents = Event::where('calendar_id', $calendar->id)
            ->where('external_source', 'ics_import')
            ->get();

        return [
            'total_imported_events' => $importedEvents->count(),
            'imported_by_month' => $importedEvents->groupBy(function ($event) {
                return $event->created_at->format('Y-m');
            })->map->count(),
            'events_with_recurrence' => $importedEvents->whereNotNull('rrule')->count(),
            'all_day_events' => $importedEvents->where('all_day', true)->count(),
            'private_events' => $importedEvents->where('is_private', true)->count(),
        ];
    }
}
