<?php

namespace App\Domain\Meetings;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Meetings\Enums\MeetingStatus;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, User $creator, array $data): Meeting
    {
        return DB::transaction(function () use ($event, $creator, $data) {
            $meeting = $event->meetings()->create($data + [
                'status' => MeetingStatus::Scheduled,
                'created_by_user_id' => $creator->id,
            ]);

            $this->audit->record(
                AuditAction::MEETING_CREATED,
                'meetings',
                ($meeting->isGeneral() ? 'Reunião geral' : 'Reunião setorial')." \"{$meeting->title}\" criada.",
                entity: $meeting,
                after: $this->snapshot($meeting),
            );

            return $meeting->load('sector', 'creator.person');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Meeting $meeting, array $data): Meeting
    {
        return DB::transaction(function () use ($meeting, $data) {
            $before = $this->snapshot($meeting);
            $meeting->fill($data);

            if (! $meeting->isDirty()) {
                return $meeting->load('sector', 'creator.person');
            }

            $meeting->save();

            $this->audit->record(
                AuditAction::MEETING_UPDATED,
                'meetings',
                "Reunião \"{$meeting->title}\" alterada.",
                entity: $meeting,
                before: $before,
                after: $this->snapshot($meeting),
            );

            return $meeting->load('sector', 'creator.person');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Meeting $meeting): array
    {
        return [
            'event_id' => $meeting->event_id,
            'sector_id' => $meeting->sector_id,
            'title' => $meeting->title,
            'description' => $meeting->description,
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'location' => $meeting->location,
            'status' => $meeting->status?->value,
        ];
    }
}
