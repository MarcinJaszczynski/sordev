<?php

namespace App\Support\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Support\Str;

final class TaskListColumn
{
    public static function sanitizeTaskText(mixed $value, int $limit = 512): string
    {
        if (! filled($value)) {
            return '';
        }

        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/\[payment-reminder:[^\]]+\]/', '', $text) ?? $text;
        $text = preg_replace('/^Link:\s*https?:\/\/\S+\s*$/mi', '', $text) ?? $text;
        $text = preg_replace('/^\s+/m', '', $text) ?? $text;
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);

        return Str::limit($text, $limit);
    }

    public static function authorLabel(Task $record): string
    {
        $source = $record->source instanceof TaskSource
            ? $record->source
            : TaskSource::tryFrom((string) ($record->source ?? TaskSource::Office->value));

        if ($source === TaskSource::System) {
            return 'System';
        }

        $record->loadMissing('author');

        return $record->author?->name ?: '—';
    }

    public static function assigneeLabel(Task $record): string
    {
        $record->loadMissing('assignee');

        return $record->assignee?->name ?: '—';
    }

    /**
     * Jedna linia „Od X dla Y” — listy, kanban, kalendarz, powiadomienia.
     */
    public static function ownershipLine(Task $record): string
    {
        return 'Od '.self::authorLabel($record).' dla '.self::assigneeLabel($record);
    }

    public static function effectiveModifiedAt(Task $record): ?\Illuminate\Support\Carbon
    {
        $created = $record->created_at;
        $updated = $record->updated_at;

        if ($updated && $created && $updated->greaterThan($created)) {
            return $updated;
        }

        return $created ?? $updated;
    }

    public static function taskCellHtml(Task $record): string
    {
        $title = e($record->title ?: '—');
        $description = self::sanitizeTaskText($record->description, 512);
        $descriptionHtml = $description !== ''
            ? '<div style="margin-top:4px;color:#374151;font-size:0.78rem;line-height:1.35;white-space:pre-wrap">'
                .e($description)
                .'</div>'
            : '';

        $commentBlock = self::latestCommentBlock($record);

        return '<div style="min-width:220px;max-width:420px">'
            .'<div style="font-weight:600;color:#111827;font-size:0.84rem;line-height:1.25">'.$title.'</div>'
            .$descriptionHtml
            .$commentBlock
            .'</div>';
    }

    public static function attachmentsCellHtml(Task $record): string
    {
        $attachments = $record->relationLoaded('attachments')
            ? $record->attachments
            : collect();

        if ($attachments->isEmpty()) {
            return '<span style="color:#9ca3af;font-size:0.78rem">—</span>';
        }

        $links = $attachments->map(function ($attachment): string {
            $url = e($attachment->preview_url ?? '#');
            $label = e($attachment->filename);

            return '<a href="'.$url.'" target="_blank" rel="noopener"'
                .' style="display:block;color:#2563eb;font-size:0.78rem;line-height:1.35;text-decoration:underline">'
                .$label
                .'</a>';
        })->implode('');

        return '<div style="min-width:120px;max-width:220px">'.$links.'</div>';
    }

    public static function contextCellHtml(Task $record): string
    {
        $parentLine = '—';
        if ($record->parent) {
            $parentUrl = e(TaskNavigation::fullViewUrl($record->parent_id));
            $parentTitle = e($record->parent->title);
            $parentLine = '<a href="'.$parentUrl.'" style="color:#2563eb;text-decoration:underline">'.$parentTitle.'</a>';
        }

        $contextType = e($record->taskable_type_label ?: 'Wolne / nieprzypisane');
        $contextRecord = e($record->taskable_label);
        $contextUrl = TaskContextRegistry::urlForRecord($record->taskable);
        $contextRecordHtml = $contextUrl
            ? '<a href="'.e($contextUrl).'" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline">'.$contextRecord.'</a>'
            : $contextRecord;

        $row = fn (string $label, string $value): string => '<tr>'
            .'<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap;vertical-align:top">'.$label.'</td>'
            .'<td style="color:#111827;font-size:0.78rem;line-height:1.25">'.$value.'</td>'
            .'</tr>';

        return '<table style="border-collapse:collapse;min-width:160px">'
            .$row('Nadrzędne:', $parentLine)
            .$row(e($contextType).':', $contextRecordHtml)
            .'</table>';
    }

    public static function duePriorityCellHtml(Task $record): string
    {
        $dueHtml = '—';
        if ($record->due_date) {
            $dueColor = self::isDueOverdue($record) ? '#dc2626' : '#111827';
            $dueHtml = '<span style="color:'.$dueColor.';font-weight:600">'
                .e($record->due_date->format('d.m.Y H:i'))
                .'</span>';
        }

        $priority = TaskPriority::normalize($record->priority instanceof TaskPriority ? $record->priority->value : (string) $record->priority);
        $isUrgent = $priority === TaskPriority::Urgent->value;
        $priorityLabel = e($isUrgent ? TaskPriority::Urgent->label() : TaskPriority::Normal->label());
        $priorityColor = $isUrgent ? '#991b1b' : '#374151';
        $priorityBg = $isUrgent ? '#fee2e2' : '#f3f4f6';

        return '<div style="font-size:0.78rem;line-height:1.35">'
            .'<div>'.$dueHtml.'</div>'
            .'<div style="margin-top:4px">'
            .'<span style="display:inline-block;padding:1px 8px;border-radius:9999px;background:'.$priorityBg.';color:'.$priorityColor.';font-size:0.72rem;font-weight:600">'
            .$priorityLabel
            .'</span>'
            .'</div>'
            .'</div>';
    }

    public static function modificationCellHtml(Task $record): string
    {
        $created = $record->created_at?->format('d.m.Y H:i') ?? '—';
        $modified = self::effectiveModifiedAt($record)?->format('d.m.Y H:i') ?? '—';

        return '<div style="font-size:0.78rem;line-height:1.35;white-space:nowrap">'
            .'<div><span style="color:#9ca3af;font-size:0.72rem">Zmieniono </span>'
            .'<span style="color:#111827;font-weight:500">'.e($modified).'</span></div>'
            .'<div style="margin-top:2px"><span style="color:#9ca3af;font-size:0.72rem">Utworzono </span>'
            .'<span style="color:#111827;font-weight:500">'.e($created).'</span></div>'
            .'</div>';
    }

    public static function isDueOverdue(Task $record): bool
    {
        if (! $record->due_date || ! $record->due_date->isPast()) {
            return false;
        }

        $statusName = $record->relationLoaded('status')
            ? (string) ($record->status?->name ?? '')
            : '';

        if ($statusName === '') {
            $record->loadMissing('status');
            $statusName = (string) ($record->status?->name ?? '');
        }

        return ! in_array($statusName, TaskQueryFilters::FINISHED_STATUS_NAMES, true);
    }

    private static function latestCommentBlock(Task $record): string
    {
        /** @var TaskComment|null $comment */
        $comment = $record->relationLoaded('comments')
            ? $record->comments->first()
            : null;

        if (! $comment) {
            return '<div style="margin-top:6px;color:#9ca3af;font-size:0.72rem">Brak komentarzy</div>';
        }

        $comment->loadMissing('author');
        $author = e($comment->author?->name ?? '—');
        $date = e($comment->created_at?->format('d.m.Y H:i') ?? '—');
        $content = e(self::sanitizeTaskText($comment->content, 512));

        $count = (int) ($record->comments_count ?? $record->comments?->count() ?? 0);
        $countLabel = $count > 1 ? ' · '.$count : '';

        return '<div style="margin-top:6px;padding:6px 8px;border-radius:8px;background:#f8fafc;border:1px solid #e5e7eb">'
            .'<div style="color:#6b7280;font-size:0.68rem;line-height:1.2">Ostatni komentarz'.$countLabel.' · '.$author.' · '.$date.'</div>'
            .'<div style="margin-top:3px;color:#374151;font-size:0.72rem;line-height:1.35;white-space:pre-wrap">'.$content.'</div>'
            .'</div>';
    }

    private static function plainText(mixed $value, int $limit): string
    {
        return self::sanitizeTaskText($value, $limit);
    }
}
