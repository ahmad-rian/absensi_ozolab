export type AcademicYear = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

export type Classroom = {
    id: number;
    academic_year_id: number;
    name: string;
    grade_level: number;
    homeroom_teacher_id: number | null;
    capacity: number;
    students_count?: number;
    academic_year?: AcademicYear;
    homeroom_teacher?: { id: number; name: string } | null;
};

export type ParentProfile = {
    id: number;
    user_id: number;
    nik: string | null;
    whatsapp_number: string;
    relation: 'AYAH' | 'IBU' | 'WALI';
    occupation: string | null;
    address: string | null;
    city: string | null;
    user?: { id: number; name: string; email: string };
    students?: Student[];
};

export type Student = {
    id: number;
    parent_profile_id: number;
    classroom_id: number | null;
    nis: string;
    nisn: string | null;
    full_name: string;
    gender: 'LAKI_LAKI' | 'PEREMPUAN';
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    photo_path: string | null;
    qr_token: string | null;
    is_active: boolean;
    classroom?: Classroom;
    parent_profile?: ParentProfile;
};

export type Attendance = {
    id: number;
    student_id: number;
    attendance_date: string;
    type: 'CHECK_IN' | 'CHECK_OUT';
    status: 'HADIR' | 'TERLAMBAT' | 'ALPA' | 'IZIN' | 'SAKIT';
    recorded_at: string;
    recorded_by: number | null;
    device_id: string | null;
    notes: string | null;
    student?: Student;
};

export type AttendanceSchedule = {
    id: number;
    classroom_id: number | null;
    day_of_week: number;
    check_in_start: string;
    check_in_end: string;
    late_threshold: string;
    check_out_start: string;
    check_out_end: string;
    is_active: boolean;
};

export type NotificationLog = {
    id: number;
    student_id: number;
    attendance_id: number | null;
    parent_profile_id: number;
    channel: 'WHATSAPP' | 'EMAIL';
    whatsapp_number: string;
    template_key: string | null;
    status: 'PENDING' | 'SENT' | 'FAILED';
    error_message: string | null;
    attempt_count: number;
    sent_at: string | null;
};

export type Setting = {
    id: number;
    key: string;
    value: unknown;
    description: string | null;
};
