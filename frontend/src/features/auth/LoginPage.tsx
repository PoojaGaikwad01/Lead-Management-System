import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { Button, TextField } from '../../components/ui'
import { errorMessage, fieldErrors } from '../../lib/api'
import { useAuth } from './AuthContext'

export function LoginPage() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (user) return <Navigate to="/" replace />

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      await login(email, password)
      navigate((location.state as { from?: string } | null)?.from ?? '/', { replace: true })
    } catch (err) {
      setError(fieldErrors(err).email ?? errorMessage(err))
      setSubmitting(false)
    }
  }

  return (
    <main className="grid min-h-screen place-items-center bg-brand-50 p-4">
      <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-lg sm:p-8">
        <p className="text-center text-2xl font-extrabold text-brand-600">Funky Monkey</p>
        <h1 className="mb-6 text-center text-sm font-medium text-gray-600">Lead Management — sign in</h1>
        <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
          {error && (
            <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
              {error}
            </p>
          )}
          <TextField
            label="Email"
            type="email"
            autoComplete="username"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          <TextField
            label="Password"
            type="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
          <Button type="submit" loading={submitting} className="mt-2">
            Sign in
          </Button>
        </form>
      </div>
    </main>
  )
}
