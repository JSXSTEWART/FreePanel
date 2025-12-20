import { useState, useEffect } from "react";
import { Card, CardBody } from "../../components/common/Card";
import Button from "../../components/common/Button";
import Modal, { ModalBody, ModalFooter } from "../../components/common/Modal";
import ConfirmDialog from "../../components/common/ConfirmDialog";
import Input, { PasswordStrength } from "../../components/common/Input";
import Badge, { StatusBadge } from "../../components/common/Badge";
import ProgressBar from "../../components/common/ProgressBar";
import EmptyState, {
  SearchEmptyState,
} from "../../components/common/EmptyState";
import {
  SkeletonTable,
  SkeletonStatCards,
} from "../../components/common/Skeleton";
import Tooltip from "../../components/common/Tooltip";
import toast from "react-hot-toast";
import {
  UserPlusIcon,
  MagnifyingGlassIcon,
  PauseCircleIcon,
  PlayCircleIcon,
  TrashIcon,
  PencilIcon,
  UsersIcon,
  CheckCircleIcon,
  XCircleIcon,
  ServerIcon,
  XMarkIcon,
} from "@heroicons/react/24/outline";
import { accountsApi, packagesApi, Account, Package } from "../../api";

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  if (bytes === -1) return "Unlimited";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB", "TB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

export default function Accounts() {
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  // Confirm dialogs
  const [deleteConfirm, setDeleteConfirm] = useState<Account | null>(null);
  const [suspendConfirm, setSuspendConfirm] = useState<Account | null>(null);

  // Form state
  const [formData, setFormData] = useState({
    username: "",
    password: "",
    email: "",
    domain: "",
    package_id: 0,
  });
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  // Stats
  const totalAccounts = accounts.length;
  const activeAccounts = accounts.filter((a) => a.status === "active").length;
  const suspendedAccounts = accounts.filter(
    (a) => a.status === "suspended",
  ).length;
  const totalDiskUsed = accounts.reduce((sum, a) => sum + a.disk_used, 0);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      const [accountsData, packagesData] = await Promise.all([
        accountsApi.list({ per_page: 100 }),
        packagesApi.list(),
      ]);
      setAccounts(accountsData.data);
      setPackages(packagesData);
      if (packagesData.length > 0) {
        const defaultPkg =
          packagesData.find((p) => p.is_default) || packagesData[0];
        setFormData((prev) => ({ ...prev, package_id: defaultPkg.id }));
      }
    } catch (error) {
      toast.error("Failed to load accounts");
    } finally {
      setLoading(false);
    }
  };

  const filteredAccounts = accounts.filter(
    (acc) =>
      acc.username.toLowerCase().includes(search.toLowerCase()) ||
      acc.domain.toLowerCase().includes(search.toLowerCase()) ||
      acc.user.email.toLowerCase().includes(search.toLowerCase()),
  );

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {};

    if (!formData.username || formData.username.length < 3) {
      errors.username = "Username must be at least 3 characters";
    } else if (formData.username.length > 16) {
      errors.username = "Username must be at most 16 characters";
    } else if (!/^[a-z0-9]+$/.test(formData.username)) {
      errors.username =
        "Username can only contain lowercase letters and numbers";
    }

    if (!formData.password || formData.password.length < 8) {
      errors.password = "Password must be at least 8 characters";
    }

    if (!formData.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      errors.email = "Please enter a valid email address";
    }

    if (
      !formData.domain ||
      !/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/.test(formData.domain)
    ) {
      errors.domain = "Please enter a valid domain name";
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleCreate = async () => {
    if (!validateForm()) return;

    try {
      setActionLoading(-1);
      await accountsApi.create(formData);
      toast.success("Account created successfully");
      setShowCreateModal(false);
      setFormData({
        username: "",
        password: "",
        email: "",
        domain: "",
        package_id: formData.package_id,
      });
      setFormErrors({});
      loadData();
    } catch (error: unknown) {
      const axiosError = error as {
        response?: { data?: { message?: string } };
      };
      toast.error(
        axiosError.response?.data?.message || "Failed to create account",
      );
    } finally {
      setActionLoading(null);
    }
  };

  const handleSuspend = async () => {
    if (!suspendConfirm) return;

    try {
      setActionLoading(suspendConfirm.id);
      await accountsApi.suspend(
        suspendConfirm.id,
        "Suspended by administrator",
      );
      toast.success("Account suspended");
      setSuspendConfirm(null);
      loadData();
    } catch (error) {
      toast.error("Failed to suspend account");
    } finally {
      setActionLoading(null);
    }
  };

  const handleUnsuspend = async (account: Account) => {
    try {
      setActionLoading(account.id);
      await accountsApi.unsuspend(account.id);
      toast.success("Account unsuspended");
      loadData();
    } catch (error) {
      toast.error("Failed to unsuspend account");
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async () => {
    if (!deleteConfirm) return;

    try {
      setActionLoading(deleteConfirm.id);
      await accountsApi.delete(deleteConfirm.id);
      toast.success("Account terminated");
      setDeleteConfirm(null);
      loadData();
    } catch (error) {
      toast.error("Failed to terminate account");
    } finally {
      setActionLoading(null);
    }
  };

  const resetForm = () => {
    setFormData({
      username: "",
      password: "",
      email: "",
      domain: "",
      package_id:
        packages.find((p) => p.is_default)?.id || packages[0]?.id || 0,
    });
    setFormErrors({});
  };

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Hosting Accounts
            </h1>
            <p className="text-gray-500">
              Manage all hosting accounts on this server
            </p>
          </div>
        </div>
        <SkeletonStatCards count={4} />
        <SkeletonTable rows={5} columns={7} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Hosting Accounts</h1>
          <p className="text-gray-500">
            Manage all hosting accounts on this server
          </p>
        </div>
        <Button
          variant="primary"
          onClick={() => {
            resetForm();
            setShowCreateModal(true);
          }}
        >
          <UserPlusIcon className="w-5 h-5 mr-2" />
          Create Account
        </Button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardBody className="flex items-center gap-4">
            <div className="p-3 bg-blue-50 rounded-xl">
              <UsersIcon className="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">
                {totalAccounts}
              </p>
              <p className="text-sm text-gray-500">Total Accounts</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="flex items-center gap-4">
            <div className="p-3 bg-green-50 rounded-xl">
              <CheckCircleIcon className="w-6 h-6 text-green-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">
                {activeAccounts}
              </p>
              <p className="text-sm text-gray-500">Active</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="flex items-center gap-4">
            <div className="p-3 bg-red-50 rounded-xl">
              <XCircleIcon className="w-6 h-6 text-red-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">
                {suspendedAccounts}
              </p>
              <p className="text-sm text-gray-500">Suspended</p>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="flex items-center gap-4">
            <div className="p-3 bg-purple-50 rounded-xl">
              <ServerIcon className="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">
                {formatBytes(totalDiskUsed)}
              </p>
              <p className="text-sm text-gray-500">Disk Used</p>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Search */}
      <div className="relative">
        <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          type="text"
          placeholder="Search accounts by username, domain, or email..."
          className="input pl-10 w-full"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        {search && (
          <button
            onClick={() => setSearch("")}
            className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
          >
            <XMarkIcon className="w-5 h-5" />
          </button>
        )}
      </div>

      {/* Accounts Table */}
      <Card>
        <CardBody className="p-0">
          {filteredAccounts.length === 0 ? (
            search ? (
              <SearchEmptyState query={search} />
            ) : (
              <EmptyState
                title="No accounts yet"
                description="Get started by creating your first hosting account."
                action={{
                  label: "Create Account",
                  onClick: () => {
                    resetForm();
                    setShowCreateModal(true);
                  },
                }}
              />
            )
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="table-header">Account</th>
                    <th className="table-header">Domain</th>
                    <th className="table-header">Package</th>
                    <th className="table-header">Disk Usage</th>
                    <th className="table-header">Status</th>
                    <th className="table-header">Created</th>
                    <th className="table-header text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {filteredAccounts.map((account) => (
                    <tr key={account.id} className="table-row">
                      <td className="table-cell">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span className="text-primary-600 font-semibold">
                              {account.username.charAt(0).toUpperCase()}
                            </span>
                          </div>
                          <div className="min-w-0">
                            <p className="font-medium text-gray-900 truncate">
                              {account.username}
                            </p>
                            <p className="text-sm text-gray-500 truncate">
                              {account.user.email}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td className="table-cell">
                        <a
                          href={`https://${account.domain}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-primary-600 hover:text-primary-700 font-medium"
                        >
                          {account.domain}
                        </a>
                      </td>
                      <td className="table-cell">
                        <Badge variant="info">
                          {account.package?.name || "Unknown"}
                        </Badge>
                      </td>
                      <td className="table-cell">
                        <div className="w-32">
                          <p className="text-sm text-gray-600 mb-1">
                            {formatBytes(account.disk_used)} /{" "}
                            {formatBytes(account.package?.disk_quota || 0)}
                          </p>
                          <ProgressBar
                            value={account.disk_used}
                            max={account.package?.disk_quota || 1}
                            size="sm"
                          />
                        </div>
                      </td>
                      <td className="table-cell">
                        <StatusBadge
                          status={account.status as "active" | "suspended"}
                        />
                      </td>
                      <td className="table-cell text-gray-500">
                        {formatDate(account.created_at)}
                      </td>
                      <td className="table-cell">
                        <div className="flex items-center justify-end gap-1">
                          <Tooltip content="Edit account">
                            <button
                              className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                              disabled={actionLoading === account.id}
                            >
                              <PencilIcon className="w-4 h-4" />
                            </button>
                          </Tooltip>
                          {account.status === "active" ? (
                            <Tooltip content="Suspend account">
                              <button
                                className="p-2 text-yellow-600 hover:text-yellow-700 hover:bg-yellow-50 rounded-lg transition-colors disabled:opacity-50"
                                disabled={actionLoading === account.id}
                                onClick={() => setSuspendConfirm(account)}
                              >
                                <PauseCircleIcon className="w-4 h-4" />
                              </button>
                            </Tooltip>
                          ) : (
                            <Tooltip content="Unsuspend account">
                              <button
                                className="p-2 text-green-600 hover:text-green-700 hover:bg-green-50 rounded-lg transition-colors disabled:opacity-50"
                                disabled={actionLoading === account.id}
                                onClick={() => handleUnsuspend(account)}
                              >
                                <PlayCircleIcon className="w-4 h-4" />
                              </button>
                            </Tooltip>
                          )}
                          <Tooltip content="Delete account">
                            <button
                              className="p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
                              disabled={actionLoading === account.id}
                              onClick={() => setDeleteConfirm(account)}
                            >
                              <TrashIcon className="w-4 h-4" />
                            </button>
                          </Tooltip>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Create Account Modal */}
      <Modal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        title="Create New Account"
        description="Set up a new hosting account for a user"
        size="lg"
      >
        <ModalBody className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Username"
              placeholder="johndoe"
              value={formData.username}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  username: e.target.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]/g, ""),
                })
              }
              maxLength={16}
              error={formErrors.username}
              hint="3-16 lowercase letters and numbers"
            />
            <div>
              <Input
                label="Password"
                type="password"
                placeholder="Min 8 characters"
                value={formData.password}
                onChange={(e) =>
                  setFormData({ ...formData, password: e.target.value })
                }
                error={formErrors.password}
                showPasswordToggle
              />
              <PasswordStrength password={formData.password} />
            </div>
          </div>
          <Input
            label="Domain"
            placeholder="example.com"
            value={formData.domain}
            onChange={(e) =>
              setFormData({ ...formData, domain: e.target.value.toLowerCase() })
            }
            error={formErrors.domain}
            hint="Primary domain for this account"
          />
          <Input
            label="Email"
            type="email"
            placeholder="user@example.com"
            value={formData.email}
            onChange={(e) =>
              setFormData({ ...formData, email: e.target.value })
            }
            error={formErrors.email}
            hint="Contact email for the account owner"
          />
          <div>
            <label className="label">Package</label>
            <select
              className="input"
              value={formData.package_id}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  package_id: parseInt(e.target.value),
                })
              }
            >
              {packages.map((pkg) => (
                <option key={pkg.id} value={pkg.id}>
                  {pkg.name} - {formatBytes(pkg.disk_quota)} Disk,{" "}
                  {formatBytes(pkg.bandwidth)} Bandwidth
                  {pkg.is_default ? " (Default)" : ""}
                </option>
              ))}
            </select>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setShowCreateModal(false)}>
            Cancel
          </Button>
          <Button
            variant="primary"
            onClick={handleCreate}
            isLoading={actionLoading === -1}
          >
            Create Account
          </Button>
        </ModalFooter>
      </Modal>

      {/* Suspend Confirmation Dialog */}
      <ConfirmDialog
        isOpen={!!suspendConfirm}
        onClose={() => setSuspendConfirm(null)}
        onConfirm={handleSuspend}
        title="Suspend Account"
        message={`Are you sure you want to suspend the account "${suspendConfirm?.username}"? The user will not be able to access their website or email until unsuspended.`}
        confirmLabel="Suspend"
        variant="warning"
        isLoading={actionLoading === suspendConfirm?.id}
      />

      {/* Delete Confirmation Dialog */}
      <ConfirmDialog
        isOpen={!!deleteConfirm}
        onClose={() => setDeleteConfirm(null)}
        onConfirm={handleDelete}
        title="Terminate Account"
        message={`Are you sure you want to permanently delete the account "${deleteConfirm?.username}"? This will remove all files, emails, and databases. This action cannot be undone.`}
        confirmLabel="Delete Permanently"
        variant="danger"
        isLoading={actionLoading === deleteConfirm?.id}
      />
    </div>
  );
}
