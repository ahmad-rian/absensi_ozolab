import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { invite } from '@/routes/general/penyelenggara';

/** Undang admin penyelenggara lewat email (tautan undangan berlaku terbatas). */
export function UndangAdmin({
    penyelenggaras,
}: {
    penyelenggaras: { id: string; name: string }[];
}) {
    const [buka, setBuka] = useState(false);
    const [penyelenggara, setPenyelenggara] = useState('');
    const form = useForm({ email: '' });

    const kirim = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(invite.url(penyelenggara), {
            preserveScroll: true,
            onSuccess: () => {
                setBuka(false);
                form.reset();
            },
        });
    };

    return (
        <>
            <Button variant="outline" onClick={() => setBuka(true)}>
                <Send className="size-4" /> Undang admin
            </Button>
            <Dialog open={buka} onOpenChange={setBuka}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Undang admin penyelenggara</DialogTitle>
                        <DialogDescription>
                            Penerima membuat sandinya sendiri dari tautan di
                            email undangan.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={kirim} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="undang-penyelenggara">
                                Penyelenggara
                            </Label>
                            <select
                                id="undang-penyelenggara"
                                required
                                value={penyelenggara}
                                onChange={(e) =>
                                    setPenyelenggara(e.target.value)
                                }
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                            >
                                <option value="" disabled>
                                    Pilih penyelenggara…
                                </option>
                                {penyelenggaras.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="undang-email">Email</Label>
                            <Input
                                id="undang-email"
                                type="email"
                                required
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                            <InputError message={form.errors.email} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setBuka(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing || !penyelenggara}
                            >
                                Kirim undangan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
