import { RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface SimpleCaptchaProps {
    onVerified: (token: string) => void;
    error?: string;
}

function generateCode(length = 6): string {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < length; i++) {
        code += chars[Math.floor(Math.random() * chars.length)];
    }
    return code;
}

function drawCaptcha(canvas: HTMLCanvasElement, code: string) {
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const w = canvas.width;
    const h = canvas.height;

    // Background
    ctx.fillStyle = '#f0f0f0';
    ctx.fillRect(0, 0, w, h);

    // Noise lines
    for (let i = 0; i < 6; i++) {
        ctx.strokeStyle = `hsl(${Math.random() * 360}, 40%, 70%)`;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(Math.random() * w, Math.random() * h);
        ctx.lineTo(Math.random() * w, Math.random() * h);
        ctx.stroke();
    }

    // Noise dots
    for (let i = 0; i < 40; i++) {
        ctx.fillStyle = `hsl(${Math.random() * 360}, 30%, 65%)`;
        ctx.beginPath();
        ctx.arc(Math.random() * w, Math.random() * h, 1.5, 0, Math.PI * 2);
        ctx.fill();
    }

    // Draw each character with random rotation/position
    const fontSize = 28;
    ctx.textBaseline = 'middle';

    const startX = 15;
    const spacing = (w - 30) / code.length;

    for (let i = 0; i < code.length; i++) {
        ctx.save();
        const x = startX + i * spacing + spacing / 2;
        const y = h / 2 + (Math.random() * 10 - 5);
        const angle = (Math.random() - 0.5) * 0.5;

        ctx.translate(x, y);
        ctx.rotate(angle);

        ctx.font = `bold ${fontSize + Math.random() * 6 - 3}px monospace`;
        ctx.fillStyle = `hsl(${200 + Math.random() * 60}, ${50 + Math.random() * 30}%, ${25 + Math.random() * 15}%)`;
        ctx.fillText(code[i], -fontSize / 4, 0);

        ctx.restore();
    }

    // Overlay line through middle
    ctx.strokeStyle = `hsl(${Math.random() * 360}, 50%, 50%)`;
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(0, h / 2 + Math.random() * 20 - 10);
    ctx.bezierCurveTo(w * 0.3, Math.random() * h, w * 0.7, Math.random() * h, w, h / 2 + Math.random() * 20 - 10);
    ctx.stroke();
}

export function SimpleCaptcha({ onVerified, error }: SimpleCaptchaProps) {
    const [code, setCode] = useState('');
    const [input, setInput] = useState('');
    const [localError, setLocalError] = useState('');
    const canvasRef = useRef<HTMLCanvasElement>(null);

    const refresh = useCallback(() => {
        const newCode = generateCode();
        setCode(newCode);
        setInput('');
        setLocalError('');
        onVerified('');
    }, [onVerified]);

    useEffect(() => {
        refresh();
    }, []);

    useEffect(() => {
        if (code && canvasRef.current) {
            drawCaptcha(canvasRef.current, code);
        }
    }, [code]);

    function handleChange(value: string) {
        const upper = value.toUpperCase();
        setInput(upper);
        setLocalError('');

        if (upper.length === code.length) {
            if (upper === code) {
                onVerified(code);
                setLocalError('');
            } else {
                setLocalError('Kode tidak cocok. Coba lagi.');
                onVerified('');
                setTimeout(refresh, 1200);
            }
        } else {
            onVerified('');
        }
    }

    const displayError = error || localError;

    return (
        <div className="grid gap-2">
            <Label className="text-sm font-medium">Verifikasi Captcha</Label>
            <div className="flex items-center gap-2">
                <canvas
                    ref={canvasRef}
                    width={200}
                    height={50}
                    className="rounded-lg border border-zinc-300 dark:border-zinc-600"
                    style={{ imageRendering: 'auto' }}
                />
                <Button type="button" variant="ghost" size="icon" onClick={refresh} title="Ganti kode">
                    <RefreshCw className="size-4" />
                </Button>
            </div>
            <Input
                value={input}
                onChange={(e) => handleChange(e.target.value)}
                placeholder="Ketik kode di atas"
                maxLength={6}
                className="font-mono text-lg tracking-widest uppercase"
                autoComplete="off"
                spellCheck={false}
            />
            {displayError && <p className="text-sm text-red-600">{displayError}</p>}
        </div>
    );
}
