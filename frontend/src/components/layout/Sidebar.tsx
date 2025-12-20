import { NavLink } from "react-router-dom";
import { clsx } from "clsx";
import {
  HomeIcon,
  GlobeAltIcon,
  EnvelopeIcon,
  CircleStackIcon,
  FolderIcon,
  LockClosedIcon,
  CubeIcon,
  ArchiveBoxIcon,
  Cog6ToothIcon,
  UsersIcon,
  ServerStackIcon,
  CpuChipIcon,
  RectangleGroupIcon,
} from "@heroicons/react/24/outline";

interface SidebarProps {
  isAdmin?: boolean;
}

const userNavItems = [
  { name: "Dashboard", href: "/", icon: HomeIcon },
  { name: "Domains", href: "/domains", icon: GlobeAltIcon },
  { name: "Email", href: "/email", icon: EnvelopeIcon },
  { name: "Databases", href: "/databases", icon: CircleStackIcon },
  { name: "Files", href: "/files", icon: FolderIcon },
  { name: "SSL/TLS", href: "/ssl", icon: LockClosedIcon },
  { name: "Apps", href: "/apps", icon: CubeIcon },
  { name: "Backups", href: "/backups", icon: ArchiveBoxIcon },
  { name: "Settings", href: "/settings", icon: Cog6ToothIcon },
];

const adminNavItems = [
  { name: "Dashboard", href: "/admin", icon: HomeIcon },
  { name: "Accounts", href: "/admin/accounts", icon: UsersIcon },
  { name: "Packages", href: "/admin/packages", icon: RectangleGroupIcon },
  { name: "Services", href: "/admin/services", icon: ServerStackIcon },
];

export default function Sidebar({ isAdmin = false }: SidebarProps) {
  const navItems = isAdmin ? adminNavItems : userNavItems;

  return (
    <aside className="fixed left-0 top-0 h-screen w-64 bg-sidebar flex flex-col z-40">
      {/* Logo */}
      <div className="h-16 flex items-center px-6 border-b border-gray-700">
        <NavLink
          to={isAdmin ? "/admin" : "/"}
          className="flex items-center space-x-3"
        >
          <div className="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center">
            <CpuChipIcon className="w-5 h-5 text-white" />
          </div>
          <span className="text-xl font-bold text-white">FreePanel</span>
        </NavLink>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto py-4 px-3">
        <ul className="space-y-1">
          {navItems.map((item) => (
            <li key={item.name}>
              <NavLink
                to={item.href}
                end={item.href === "/" || item.href === "/admin"}
                className={({ isActive }) =>
                  clsx("sidebar-item", isActive && "sidebar-item-active")
                }
              >
                <item.icon className="w-5 h-5 mr-3" />
                {item.name}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      {/* Footer */}
      <div className="p-4 border-t border-gray-700">
        <p className="text-xs text-gray-400 text-center">FreePanel v1.0.0</p>
      </div>
    </aside>
  );
}
