import * as LabelPrimitive from "@radix-ui/react-label"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * `required` menempelkan bintang merah, bukan diketik tangan di tiap label.
 *
 * Pendaftar mengeluh tidak tahu mana yang wajib sampai formnya ditolak, dan
 * menyebar `*` di puluhan label berarti sebagiannya pasti tertinggal saat aturan
 * validasinya berubah. `aria-hidden` karena pembaca layar sudah mendapat
 * kewajibannya dari `required` pada input-nya sendiri.
 */
function Label({
  className,
  children,
  required,
  ...props
}: React.ComponentProps<typeof LabelPrimitive.Root> & { required?: boolean }) {
  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn(
        "text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
      )}
      {...props}
    >
      {children}
      {required && (
        <span aria-hidden className="ml-0.5 text-red-600 dark:text-red-400">
          *
        </span>
      )}
    </LabelPrimitive.Root>
  )
}

export { Label }
