import { Head, usePage } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import { FiturTab, type FeatureCatalog } from '@/components/pengaturan/fitur-tab';
import { NotifikasiTab } from '@/components/pengaturan/notifikasi-tab';
import {
    SETTINGS_TABS,
    resolveSettingsTab,
    type SettingsSection,
    type SettingsValues,
} from '@/components/pengaturan/settings-tabs';
import { SholatTab } from '@/components/pengaturan/sholat-tab';
import { TampilanTab } from '@/components/pengaturan/tampilan-tab';
import { UmumTab } from '@/components/pengaturan/umum-tab';
import { PageHeader } from '@/components/shared/page-header';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import type { SchoolFeatureMap } from '@/types';

type Props = {
    settings: SettingsValues;
    features: SchoolFeatureMap;
    featureCatalog: FeatureCatalog;
    logoUrl: string;
    faviconUrl: string;
};

export default function PengaturanIndex({ settings, features, featureCatalog, logoUrl, faviconUrl }: Props) {
    const page = usePage();
    const [tab, setTab] = useState<SettingsSection>(() => resolveSettingsTab(page.url));

    // Tiap tab memegang useForm sendiri dan melaporkan status kotornya ke sini,
    // supaya penjaga pindah-tab tahu ada perubahan yang belum disimpan tanpa
    // harus mengangkat seluruh state form ke halaman ini.
    const dirty = useRef<Partial<Record<SettingsSection, boolean>>>({});
    const [dirtyTick, setDirtyTick] = useState(0);

    const markDirty = useCallback((section: SettingsSection) => {
        return (value: boolean) => {
            if (dirty.current[section] === value) {
                return;
            }

            dirty.current[section] = value;
            setDirtyTick((tick) => tick + 1);
        };
    }, []);

    function changeTab(value: string) {
        const next = value as SettingsSection;

        if (dirty.current[tab] && ! window.confirm('Ada perubahan yang belum disimpan. Tinggalkan tab ini?')) {
            return;
        }

        setTab(next);

        // replaceState, bukan pushState: pindah tab bukan navigasi. Admin yang
        // menekan Back mengharapkan keluar dari Pengaturan, bukan menyusuri
        // lima tab mundur satu per satu.
        const url = new URL(window.location.href);
        url.searchParams.set('tab', next);
        window.history.replaceState({}, '', url.toString());
    }

    return (
        <>
            <Head title="Pengaturan Situs" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Pengaturan Situs" description="Kelola konfigurasi aplikasi absensi" />

                <Tabs value={tab} onValueChange={changeTab}>
                    <TabsList>
                        {SETTINGS_TABS.map(({ value, label, icon: Icon }) => (
                            <TabsTrigger key={value} value={value}>
                                <Icon className="size-4" />
                                {label}
                                {dirty.current[value] && (
                                    <span
                                        key={dirtyTick}
                                        className="bg-primary size-1.5 rounded-full"
                                        title="Ada perubahan yang belum disimpan"
                                    />
                                )}
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="umum">
                        <UmumTab settings={settings} onDirtyChange={markDirty('umum')} />
                    </TabsContent>

                    <TabsContent value="tampilan">
                        <TampilanTab logoUrl={logoUrl} faviconUrl={faviconUrl} />
                    </TabsContent>

                    <TabsContent value="fitur">
                        <FiturTab features={features} catalog={featureCatalog} onDirtyChange={markDirty('fitur')} />
                    </TabsContent>

                    <TabsContent value="sholat">
                        <SholatTab settings={settings} onDirtyChange={markDirty('sholat')} />
                    </TabsContent>

                    <TabsContent value="notifikasi">
                        <NotifikasiTab
                            settings={settings}
                            features={features}
                            onDirtyChange={markDirty('notifikasi')}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

PengaturanIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pengaturan', href: '/admin/pengaturan' },
    ],
};
