import KartuBebasLayoutEditor from '@/pages/kartu-bebas/layouts/editor';
import { dashboard } from '@/routes/penyelenggara';
import { update } from '@/routes/penyelenggara/kartu';
import { index } from '@/routes/penyelenggara/program';

type EditorProps = Parameters<typeof KartuBebasLayoutEditor>[0];

type Props = Omit<EditorProps, 'saveUrl'> & {
    program: { id: string; name: string };
};

/** Editor kartu Kartu Bebas, disimpan ke layout program penyelenggara. */
export default function ProgramLayoutKartu({
    program,
    form,
    frames,
    datasets,
}: Props) {
    return (
        <KartuBebasLayoutEditor
            form={form}
            frames={frames}
            datasets={datasets}
            saveUrl={update.url(program.id)}
        />
    );
}

ProgramLayoutKartu.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Program', href: index() },
    ],
};
