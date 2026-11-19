import { NavLink, Outlet } from 'react-router-dom'
import { focusRing } from '../../components/ui'

const TABS = [
  { to: '/settings', label: 'General', end: true },
  { to: '/settings/lead-types', label: 'Lead types', end: false },
  { to: '/settings/users', label: 'Users', end: false },
  { to: '/settings/branches', label: 'Branches', end: false },
  { to: '/settings/integrations', label: 'Portal integration', end: false },
]

export function SettingsLayout() {
  return (
    <>
      <nav aria-label="Settings" className="mb-5 flex gap-1 overflow-x-auto border-b border-gray-200">
        {TABS.map((tab) => (
          <NavLink
            key={tab.to}
            to={tab.to}
            end={tab.end}
            className={({ isActive }) =>
              `flex min-h-11 shrink-0 items-center border-b-2 px-3 text-sm font-semibold ${focusRing} ${
                isActive ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-600 hover:text-gray-900'
              }`
            }
          >
            {tab.label}
          </NavLink>
        ))}
      </nav>
      <Outlet />
    </>
  )
}
