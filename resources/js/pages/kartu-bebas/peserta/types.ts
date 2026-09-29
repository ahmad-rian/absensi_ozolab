export type ParticipantField = {
    key: string;
    label: string;
    type: string;
    required?: boolean;
    options?: string[];
};
export type Participant = {
    id: string;
    layout_id: string;
    layout_name: string;
    fields: ParticipantField[];
    data: Record<string, string | number | null>;
    status: 'draft' | 'processing' | 'completed' | 'failed';
    error: string | null;
    photo_url: string | null;
    original_photo_url: string | null;
    preview_url: string | null;
    download_url: string | null;
    updated_at: string;
};
export const statusLabels = {
    draft: 'Draf',
    processing: 'Sedang diproses',
    completed: 'Selesai',
    failed: 'Gagal',
};
export function participantName(participant: Participant): string {
    const name = participant.data.nama ?? participant.data.name;

    if (name) {
        return String(name);
    }

    const field = participant.fields.find(
        (field) => field.type !== 'photo' && participant.data[field.key],
    );

    return field ? String(participant.data[field.key]) : participant.id;
}
