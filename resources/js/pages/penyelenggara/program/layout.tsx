import { SaklarFormPublik } from '@/components/penyelenggara/saklar-form-publik';
import KartuBebasLayoutEditor from '@/pages/kartu-bebas/layouts/editor';
import { dashboard } from '@/routes/penyelenggara';
import { update } from '@/routes/penyelenggara/kartu';
import { index } from '@/routes/penyelenggara/program';

type EditorProps = Parameters<typeof KartuBebasLayoutEditor>[0];

type Props = Omit<EditorProps, 'saveUrl'> & {
    program: { id: string; name: string };
    formPublik: {
        aktif: boolean;
        url: string;
        terbuka: boolean;
        arsip: boolean;
    };
};

/** Editor kartu Kartu Bebas, disimpan ke layout program penyelenggara. */
export default function ProgramLayoutKartu({
    program,
    form,
    frames,
    datasets,
    formPublik,
}: Props) {
    return (
        <>
            <div className="px-4 pt-4 md:px-6 md:pt-6">
                <SaklarFormPublik
                    item={{
                        program_id: program.id,
                        program: `Form pendaftaran publik · ${program.name}`,
                        ...formPublik,
                    }}
                />
            </div>
            <KartuBebasLayoutEditor
                form={form}
                frames={frames}
                datasets={datasets}
                saveUrl={update.url(program.id)}
            />
        </>
    );
}

ProgramLayoutKartu.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Program', href: index() },
    ],
};
