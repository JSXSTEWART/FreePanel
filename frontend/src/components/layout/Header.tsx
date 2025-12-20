import { Fragment } from "react";
import { Menu, Transition } from "@headlessui/react";
import { useAuth } from "../../hooks/useAuth";
import {
  Bars3Icon,
  UserCircleIcon,
  ArrowRightOnRectangleIcon,
  Cog6ToothIcon,
} from "@heroicons/react/24/outline";
import { clsx } from "clsx";

interface HeaderProps {
  onMenuClick?: () => void;
  title?: string;
}

export default function Header({ onMenuClick, title }: HeaderProps) {
  const { user, logout } = useAuth();

  return (
    <header className="sticky top-0 z-30 bg-white border-b border-gray-200">
      <div className="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
        {/* Left side */}
        <div className="flex items-center">
          <button
            onClick={onMenuClick}
            className="lg:hidden p-2 -ml-2 text-gray-500 hover:text-gray-700"
          >
            <Bars3Icon className="w-6 h-6" />
          </button>
          {title && (
            <h1 className="ml-2 lg:ml-0 text-xl font-semibold text-gray-900">
              {title}
            </h1>
          )}
        </div>

        {/* Right side */}
        <div className="flex items-center space-x-4">
          {/* User info for larger screens */}
          {user?.account && (
            <div className="hidden md:block text-right">
              <p className="text-sm font-medium text-gray-900">
                {user.account.domain}
              </p>
              <p className="text-xs text-gray-500">{user.account.package}</p>
            </div>
          )}

          {/* User menu */}
          <Menu as="div" className="relative">
            <Menu.Button className="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100">
              <UserCircleIcon className="w-8 h-8 text-gray-400" />
              <span className="hidden sm:block text-sm font-medium text-gray-700">
                {user?.username}
              </span>
            </Menu.Button>

            <Transition
              as={Fragment}
              enter="transition ease-out duration-100"
              enterFrom="transform opacity-0 scale-95"
              enterTo="transform opacity-100 scale-100"
              leave="transition ease-in duration-75"
              leaveFrom="transform opacity-100 scale-100"
              leaveTo="transform opacity-0 scale-95"
            >
              <Menu.Items className="absolute right-0 mt-2 w-56 origin-top-right bg-white rounded-xl shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                <div className="p-1">
                  <div className="px-4 py-3 border-b border-gray-100">
                    <p className="text-sm font-medium text-gray-900">
                      {user?.username}
                    </p>
                    <p className="text-xs text-gray-500 truncate">
                      {user?.email}
                    </p>
                  </div>

                  <Menu.Item>
                    {({ active }) => (
                      <a
                        href="/settings"
                        className={clsx(
                          "flex items-center px-4 py-2 text-sm text-gray-700 rounded-lg mt-1",
                          active && "bg-gray-100",
                        )}
                      >
                        <Cog6ToothIcon className="w-5 h-5 mr-3 text-gray-400" />
                        Settings
                      </a>
                    )}
                  </Menu.Item>

                  <Menu.Item>
                    {({ active }) => (
                      <button
                        onClick={logout}
                        className={clsx(
                          "flex items-center w-full px-4 py-2 text-sm text-red-600 rounded-lg",
                          active && "bg-red-50",
                        )}
                      >
                        <ArrowRightOnRectangleIcon className="w-5 h-5 mr-3" />
                        Sign out
                      </button>
                    )}
                  </Menu.Item>
                </div>
              </Menu.Items>
            </Transition>
          </Menu>
        </div>
      </div>
    </header>
  );
}
