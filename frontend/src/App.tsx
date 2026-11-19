import { Navigate, Route, Routes } from 'react-router-dom'
import { AppLayout } from './components/AppLayout'
import { RequireAuth, RequireRole } from './features/auth/AuthContext'
import { LoginPage } from './features/auth/LoginPage'
import { BranchesPage } from './features/branches/BranchesPage'
import { DeveloperPage } from './features/developer/DeveloperPage'
import { ImportDetailPage } from './features/imports/ImportDetailPage'
import { ImportsPage } from './features/imports/ImportsPage'
import { InboxPage } from './features/inbox/InboxPage'
import { lazy, Suspense } from 'react'
import { ListSkeleton } from './components/ui'
import { GeneralSettingsPage } from './features/settings/GeneralSettingsPage'
import { IntegrationsPage } from './features/settings/IntegrationsPage'
import { LeadTypesPage } from './features/settings/LeadTypesPage'
import { SettingsLayout } from './features/settings/SettingsLayout'
import { UsersPage } from './features/users/UsersPage'

const ReportsPage = lazy(() => import('./features/reports/ReportsPage').then((m) => ({ default: m.ReportsPage })))

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<RequireAuth />}>
        <Route element={<AppLayout />}>
          <Route index element={<InboxPage />} />
          <Route path="leads/:leadId" element={<InboxPage />} />
          <Route element={<RequireRole roles={['super_admin', 'admin']} />}>
            <Route path="imports" element={<ImportsPage />} />
            <Route path="imports/:importId" element={<ImportDetailPage />} />
            <Route
              path="reports"
              element={
                <Suspense fallback={<ListSkeleton rows={4} />}>
                  <ReportsPage />
                </Suspense>
              }
            />
          </Route>
          <Route element={<RequireRole roles={['super_admin']} />}>
            <Route path="settings" element={<SettingsLayout />}>
              <Route index element={<GeneralSettingsPage />} />
              <Route path="lead-types" element={<LeadTypesPage />} />
              <Route path="users" element={<UsersPage />} />
              <Route path="branches" element={<BranchesPage />} />
              <Route path="integrations" element={<IntegrationsPage />} />
            </Route>
            {import.meta.env.DEV && <Route path="simulator" element={<DeveloperPage />} />}
          </Route>
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
