import { useState, useEffect } from "react";
import { Card, CardBody } from "../../components/common/Card";
import Button from "../../components/common/Button";
import toast from "react-hot-toast";
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  DocumentDuplicateIcon,
} from "@heroicons/react/24/outline";
import { packagesApi, Package } from "../../api";

function formatLimit(value: number, unit: string = ""): string {
  if (value === -1) return "Unlimited";
  if (unit === "bytes") {
    if (value === 0) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB", "TB"];
    const i = Math.floor(Math.log(value) / Math.log(k));
    return parseFloat((value / Math.pow(k, i)).toFixed(0)) + " " + sizes[i];
  }
  return `${value}${unit}`;
}

interface FormData {
  name: string;
  disk_quota: number;
  bandwidth: number;
  max_addon_domains: number;
  max_subdomains: number;
  max_email_accounts: number;
  max_databases: number;
  max_ftp_accounts: number;
  max_parked_domains: number;
  is_default: boolean;
}

const defaultFormData: FormData = {
  name: "",
  disk_quota: 5 * 1024 * 1024 * 1024, // 5 GB
  bandwidth: 50 * 1024 * 1024 * 1024, // 50 GB
  max_addon_domains: 5,
  max_subdomains: 25,
  max_email_accounts: 50,
  max_databases: 10,
  max_ftp_accounts: 10,
  max_parked_domains: 5,
  is_default: false,
};

export default function Packages() {
  const [packages, setPackages] = useState<Package[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingPackage, setEditingPackage] = useState<Package | null>(null);
  const [formData, setFormData] = useState<FormData>(defaultFormData);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      const data = await packagesApi.list();
      setPackages(data);
    } catch (error) {
      toast.error("Failed to load packages");
    } finally {
      setLoading(false);
    }
  };

  const openCreateModal = () => {
    setEditingPackage(null);
    setFormData(defaultFormData);
    setShowModal(true);
  };

  const openEditModal = (pkg: Package) => {
    setEditingPackage(pkg);
    setFormData({
      name: pkg.name,
      disk_quota: pkg.disk_quota,
      bandwidth: pkg.bandwidth,
      max_addon_domains: pkg.max_addon_domains,
      max_subdomains: pkg.max_subdomains,
      max_email_accounts: pkg.max_email_accounts,
      max_databases: pkg.max_databases,
      max_ftp_accounts: pkg.max_ftp_accounts,
      max_parked_domains: pkg.max_parked_domains,
      is_default: pkg.is_default,
    });
    setShowModal(true);
  };

  const handleSave = async () => {
    try {
      if (!formData.name) {
        toast.error("Please enter a package name");
        return;
      }

      setActionLoading(-1);

      if (editingPackage) {
        await packagesApi.update(editingPackage.id, formData);
        toast.success("Package updated successfully");
      } else {
        await packagesApi.create(formData);
        toast.success("Package created successfully");
      }

      setShowModal(false);
      loadData();
    } catch (error: unknown) {
      const axiosError = error as {
        response?: { data?: { message?: string } };
      };
      toast.error(
        axiosError.response?.data?.message || "Failed to save package",
      );
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (pkg: Package) => {
    if (pkg.accounts_count && pkg.accounts_count > 0) {
      toast.error("Cannot delete package with active accounts");
      return;
    }

    if (pkg.is_default) {
      toast.error("Cannot delete the default package");
      return;
    }

    if (!confirm(`Are you sure you want to delete package "${pkg.name}"?`)) {
      return;
    }

    try {
      setActionLoading(pkg.id);
      await packagesApi.delete(pkg.id);
      toast.success("Package deleted successfully");
      loadData();
    } catch (error: unknown) {
      const axiosError = error as {
        response?: { data?: { message?: string } };
      };
      toast.error(
        axiosError.response?.data?.message || "Failed to delete package",
      );
    } finally {
      setActionLoading(null);
    }
  };

  const handleDuplicate = async (pkg: Package) => {
    try {
      setActionLoading(pkg.id);
      await packagesApi.create({
        ...pkg,
        name: `${pkg.name} (Copy)`,
        is_default: false,
      });
      toast.success("Package duplicated successfully");
      loadData();
    } catch (error) {
      toast.error("Failed to duplicate package");
    } finally {
      setActionLoading(null);
    }
  };

  // Helper to convert bytes to GB for input
  const bytesToGB = (bytes: number): number => {
    if (bytes === -1) return -1;
    return Math.round(bytes / (1024 * 1024 * 1024));
  };

  // Helper to convert GB to bytes for storage
  const gbToBytes = (gb: number): number => {
    if (gb === -1) return -1;
    return gb * 1024 * 1024 * 1024;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Hosting Packages</h1>
          <p className="text-gray-500">
            Define hosting plans with resource limits
          </p>
        </div>
        <Button variant="primary" onClick={openCreateModal}>
          <PlusIcon className="w-5 h-5 mr-2" />
          Create Package
        </Button>
      </div>

      {/* Packages Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {packages.length === 0 ? (
          <Card className="col-span-full">
            <CardBody className="text-center py-8">
              <p className="text-gray-500">
                No packages found. Create your first package to get started.
              </p>
            </CardBody>
          </Card>
        ) : (
          packages.map((pkg) => (
            <Card
              key={pkg.id}
              className={pkg.is_default ? "ring-2 ring-primary-500" : ""}
            >
              <CardBody>
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">
                      {pkg.name}
                    </h3>
                    {pkg.is_default && (
                      <span className="text-xs text-primary-600 font-medium">
                        Default Package
                      </span>
                    )}
                  </div>
                  <div className="flex space-x-1">
                    <button
                      className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50"
                      title="Duplicate"
                      onClick={() => handleDuplicate(pkg)}
                      disabled={actionLoading === pkg.id}
                    >
                      <DocumentDuplicateIcon className="w-4 h-4" />
                    </button>
                    <button
                      className="p-1 text-gray-400 hover:text-primary-600 disabled:opacity-50"
                      title="Edit"
                      onClick={() => openEditModal(pkg)}
                      disabled={actionLoading === pkg.id}
                    >
                      <PencilIcon className="w-4 h-4" />
                    </button>
                    <button
                      className="p-1 text-gray-400 hover:text-red-600 disabled:opacity-50"
                      title="Delete"
                      onClick={() => handleDelete(pkg)}
                      disabled={
                        actionLoading === pkg.id ||
                        pkg.is_default ||
                        (pkg.accounts_count || 0) > 0
                      }
                    >
                      <TrashIcon className="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <div className="space-y-3">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Disk Space</span>
                    <span className="font-medium text-gray-900">
                      {formatLimit(pkg.disk_quota, "bytes")}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Bandwidth</span>
                    <span className="font-medium text-gray-900">
                      {formatLimit(pkg.bandwidth, "bytes")}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Domains</span>
                    <span className="font-medium text-gray-900">
                      {formatLimit(pkg.max_addon_domains)}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Subdomains</span>
                    <span className="font-medium text-gray-900">
                      {formatLimit(pkg.max_subdomains)}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Email Accounts</span>
                    <span className="font-medium text-gray-900">
                      {formatLimit(pkg.max_email_accounts)}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Databases</span>
                    <span className="font-medium text-gray-900">
                      {formatLimit(pkg.max_databases)}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">FTP Accounts</span>
                    <span className="font-medium text-gray-900">
                      {formatLimit(pkg.max_ftp_accounts)}
                    </span>
                  </div>
                </div>

                <div className="mt-4 pt-4 border-t border-gray-200">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-500">
                      Accounts using this package
                    </span>
                    <span className="text-lg font-semibold text-primary-600">
                      {pkg.accounts_count || 0}
                    </span>
                  </div>
                </div>
              </CardBody>
            </Card>
          ))
        )}
      </div>

      {/* Feature Comparison Table */}
      {packages.length > 0 && (
        <Card>
          <CardBody>
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              Package Comparison
            </h3>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Feature
                    </th>
                    {packages.map((pkg) => (
                      <th
                        key={pkg.id}
                        className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase"
                      >
                        {pkg.name}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  <tr>
                    <td className="px-6 py-3 text-sm font-medium text-gray-900">
                      Disk Space
                    </td>
                    {packages.map((pkg) => (
                      <td
                        key={pkg.id}
                        className="px-6 py-3 text-sm text-center text-gray-500"
                      >
                        {formatLimit(pkg.disk_quota, "bytes")}
                      </td>
                    ))}
                  </tr>
                  <tr>
                    <td className="px-6 py-3 text-sm font-medium text-gray-900">
                      Bandwidth
                    </td>
                    {packages.map((pkg) => (
                      <td
                        key={pkg.id}
                        className="px-6 py-3 text-sm text-center text-gray-500"
                      >
                        {formatLimit(pkg.bandwidth, "bytes")}
                      </td>
                    ))}
                  </tr>
                  <tr>
                    <td className="px-6 py-3 text-sm font-medium text-gray-900">
                      Addon Domains
                    </td>
                    {packages.map((pkg) => (
                      <td
                        key={pkg.id}
                        className="px-6 py-3 text-sm text-center text-gray-500"
                      >
                        {formatLimit(pkg.max_addon_domains)}
                      </td>
                    ))}
                  </tr>
                  <tr>
                    <td className="px-6 py-3 text-sm font-medium text-gray-900">
                      Email Accounts
                    </td>
                    {packages.map((pkg) => (
                      <td
                        key={pkg.id}
                        className="px-6 py-3 text-sm text-center text-gray-500"
                      >
                        {formatLimit(pkg.max_email_accounts)}
                      </td>
                    ))}
                  </tr>
                  <tr>
                    <td className="px-6 py-3 text-sm font-medium text-gray-900">
                      MySQL Databases
                    </td>
                    {packages.map((pkg) => (
                      <td
                        key={pkg.id}
                        className="px-6 py-3 text-sm text-center text-gray-500"
                      >
                        {formatLimit(pkg.max_databases)}
                      </td>
                    ))}
                  </tr>
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Create/Edit Package Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                {editingPackage ? "Edit Package" : "Create New Package"}
              </h3>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="label">Package Name *</label>
                <input
                  type="text"
                  className="input"
                  placeholder="e.g., Starter, Business, Premium"
                  value={formData.name}
                  onChange={(e) =>
                    setFormData({ ...formData, name: e.target.value })
                  }
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">Disk Quota (GB)</label>
                  <input
                    type="number"
                    className="input"
                    placeholder="-1 for unlimited"
                    value={bytesToGB(formData.disk_quota)}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        disk_quota: gbToBytes(parseInt(e.target.value) || 0),
                      })
                    }
                  />
                  <p className="text-xs text-gray-500 mt-1">-1 for unlimited</p>
                </div>
                <div>
                  <label className="label">Bandwidth (GB)</label>
                  <input
                    type="number"
                    className="input"
                    placeholder="-1 for unlimited"
                    value={bytesToGB(formData.bandwidth)}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        bandwidth: gbToBytes(parseInt(e.target.value) || 0),
                      })
                    }
                  />
                  <p className="text-xs text-gray-500 mt-1">-1 for unlimited</p>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">Max Addon Domains</label>
                  <input
                    type="number"
                    className="input"
                    value={formData.max_addon_domains}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        max_addon_domains: parseInt(e.target.value) || 0,
                      })
                    }
                  />
                </div>
                <div>
                  <label className="label">Max Subdomains</label>
                  <input
                    type="number"
                    className="input"
                    value={formData.max_subdomains}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        max_subdomains: parseInt(e.target.value) || 0,
                      })
                    }
                  />
                </div>
              </div>
              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="label">Email Accounts</label>
                  <input
                    type="number"
                    className="input"
                    value={formData.max_email_accounts}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        max_email_accounts: parseInt(e.target.value) || 0,
                      })
                    }
                  />
                </div>
                <div>
                  <label className="label">Databases</label>
                  <input
                    type="number"
                    className="input"
                    value={formData.max_databases}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        max_databases: parseInt(e.target.value) || 0,
                      })
                    }
                  />
                </div>
                <div>
                  <label className="label">FTP Accounts</label>
                  <input
                    type="number"
                    className="input"
                    value={formData.max_ftp_accounts}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        max_ftp_accounts: parseInt(e.target.value) || 0,
                      })
                    }
                  />
                </div>
              </div>
              <div className="flex items-center">
                <input
                  type="checkbox"
                  id="isDefault"
                  className="h-4 w-4 text-primary-600 rounded"
                  checked={formData.is_default}
                  onChange={(e) =>
                    setFormData({ ...formData, is_default: e.target.checked })
                  }
                />
                <label
                  htmlFor="isDefault"
                  className="ml-2 text-sm text-gray-700"
                >
                  Set as default package
                </label>
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
              <Button variant="secondary" onClick={() => setShowModal(false)}>
                Cancel
              </Button>
              <Button
                variant="primary"
                onClick={handleSave}
                disabled={actionLoading === -1}
              >
                {actionLoading === -1
                  ? "Saving..."
                  : editingPackage
                    ? "Update Package"
                    : "Create Package"}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
