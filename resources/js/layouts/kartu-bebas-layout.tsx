import GeneralLayout from '@/layouts/general-layout';
import type { AppLayoutProps } from '@/types';

/**
 * Halaman haji lama (rute `/kartu-bebas/*`) kini tampil di dalam workspace
 * Absensi General (spec 009). Rute, data, dan link scan kartu tercetak tidak
 * berubah — hanya cangkang tampilannya.
 */
export default function KartuBebasLayout(props: AppLayoutProps) {
    return <GeneralLayout {...props} />;
}
