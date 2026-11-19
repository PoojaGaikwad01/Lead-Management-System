import { useEffect, useId, useRef, type ReactNode } from 'react'
import { Button } from './ui'

export function Modal({
  open,
  title,
  onClose,
  children,
  variant = 'dialog',
}: {
  open: boolean
  title: string
  onClose: () => void
  children: ReactNode
  variant?: 'dialog' | 'drawer'
}) {
  const ref = useRef<HTMLDialogElement>(null)
  const titleId = useId()
  const drawer = variant === 'drawer'

  useEffect(() => {
    const dialog = ref.current
    if (!dialog) return
    if (open && !dialog.open) dialog.showModal()
    if (!open && dialog.open) dialog.close()
  }, [open])

  return (
    <dialog
      ref={ref}
      onClose={onClose}
      aria-labelledby={titleId}
      className={
        drawer
          ? 'm-0 ml-auto h-dvh max-h-dvh w-full max-w-md bg-white p-0 shadow-xl backdrop:bg-black/40'
          : 'm-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl bg-white p-0 shadow-xl backdrop:bg-black/40'
      }
    >
      {open && (
        <div className={drawer ? 'flex h-full flex-col' : 'p-5'}>
          <div className={`flex items-center justify-between gap-4 ${drawer ? 'border-b border-gray-200 px-4 py-2' : 'mb-4'}`}>
            <h2 id={titleId} className="text-lg font-bold text-gray-900">
              {title}
            </h2>
            <Button variant="ghost" className="px-3" onClick={onClose} aria-label="Close">
              ✕
            </Button>
          </div>
          {drawer ? <div className="min-h-0 flex-1 overflow-y-auto">{children}</div> : children}
        </div>
      )}
    </dialog>
  )
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel,
  loading,
  onConfirm,
  onClose,
}: {
  open: boolean
  title: string
  message: string
  confirmLabel: string
  loading: boolean
  onConfirm: () => void
  onClose: () => void
}) {
  return (
    <Modal open={open} title={title} onClose={onClose}>
      <p className="text-sm text-gray-700">{message}</p>
      <div className="mt-5 flex justify-end gap-2">
        <Button variant="secondary" onClick={onClose}>
          Cancel
        </Button>
        <Button variant="danger" loading={loading} onClick={onConfirm}>
          {confirmLabel}
        </Button>
      </div>
    </Modal>
  )
}
