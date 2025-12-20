import { useState, useEffect } from "react";
import { Card, CardBody } from "../../components/common/Card";
import Button from "../../components/common/Button";
import toast from "react-hot-toast";
import {
  PlayIcon,
  StopIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
} from "@heroicons/react/24/outline";
import { servicesApi, Service } from "../../api";

function StatusBadge({ service }: { service: Service }) {
  if (service.is_running) {
    return (
      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
        <CheckCircleIcon className="w-4 h-4 mr-1" />
        Running
      </span>
    );
  }

  if (service.status === "failed" || service.status === "error") {
    return (
      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
        <ExclamationTriangleIcon className="w-4 h-4 mr-1" />
        Error
      </span>
    );
  }

  return (
    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
      <XCircleIcon className="w-4 h-4 mr-1" />
      Stopped
    </span>
  );
}

export default function Services() {
  const [services, setServices] = useState<Service[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  useEffect(() => {
    loadServices();
  }, []);

  const loadServices = async () => {
    try {
      setLoading(true);
      const data = await servicesApi.list();
      setServices(data);
    } catch (error) {
      toast.error("Failed to load services");
    } finally {
      setLoading(false);
    }
  };

  const handleStart = async (serviceId: string) => {
    try {
      setActionLoading(`${serviceId}-start`);
      await servicesApi.start(serviceId);
      toast.success("Service started successfully");
      loadServices();
    } catch (error: unknown) {
      const axiosError = error as {
        response?: { data?: { message?: string } };
      };
      toast.error(
        axiosError.response?.data?.message || "Failed to start service",
      );
    } finally {
      setActionLoading(null);
    }
  };

  const handleStop = async (serviceId: string) => {
    try {
      setActionLoading(`${serviceId}-stop`);
      await servicesApi.stop(serviceId);
      toast.success("Service stopped successfully");
      loadServices();
    } catch (error: unknown) {
      const axiosError = error as {
        response?: { data?: { message?: string } };
      };
      toast.error(
        axiosError.response?.data?.message || "Failed to stop service",
      );
    } finally {
      setActionLoading(null);
    }
  };

  const handleRestart = async (serviceId: string) => {
    try {
      setActionLoading(`${serviceId}-restart`);
      await servicesApi.restart(serviceId);
      toast.success("Service restarted successfully");
      loadServices();
    } catch (error: unknown) {
      const axiosError = error as {
        response?: { data?: { message?: string } };
      };
      toast.error(
        axiosError.response?.data?.message || "Failed to restart service",
      );
    } finally {
      setActionLoading(null);
    }
  };

  const handleRefresh = () => {
    loadServices();
    toast.success("Service status refreshed");
  };

  const runningCount = services.filter((s) => s.is_running).length;
  const stoppedCount = services.filter((s) => !s.is_running).length;

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
          <h1 className="text-2xl font-bold text-gray-900">Service Manager</h1>
          <p className="text-gray-500">Monitor and control server services</p>
        </div>
        <Button variant="secondary" onClick={handleRefresh}>
          <ArrowPathIcon className="w-5 h-5 mr-2" />
          Refresh Status
        </Button>
      </div>

      {/* Summary */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardBody className="flex items-center">
            <div className="p-3 bg-green-100 rounded-lg mr-4">
              <CheckCircleIcon className="w-8 h-8 text-green-600" />
            </div>
            <div>
              <div className="text-2xl font-bold text-gray-900">
                {runningCount}
              </div>
              <div className="text-sm text-gray-500">Services Running</div>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="flex items-center">
            <div className="p-3 bg-gray-100 rounded-lg mr-4">
              <XCircleIcon className="w-8 h-8 text-gray-600" />
            </div>
            <div>
              <div className="text-2xl font-bold text-gray-900">
                {stoppedCount}
              </div>
              <div className="text-sm text-gray-500">Services Stopped</div>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="flex items-center">
            <div className="p-3 bg-blue-100 rounded-lg mr-4">
              <ArrowPathIcon className="w-8 h-8 text-blue-600" />
            </div>
            <div>
              <div className="text-2xl font-bold text-gray-900">
                {services.length}
              </div>
              <div className="text-sm text-gray-500">Total Services</div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Services Table */}
      <Card>
        <CardBody className="p-0">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Service
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Uptime
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  PID
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Memory
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  CPU
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {services.length === 0 ? (
                <tr>
                  <td
                    colSpan={7}
                    className="px-6 py-8 text-center text-gray-500"
                  >
                    No services found
                  </td>
                </tr>
              ) : (
                services.map((service) => (
                  <tr key={service.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div>
                        <div className="font-medium text-gray-900">
                          {service.display_name}
                        </div>
                        <div className="text-sm text-gray-500">
                          {service.service_name}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <StatusBadge service={service} />
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {service.uptime || "-"}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {service.pid || "-"}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {service.memory || "-"}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {service.cpu || "-"}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex space-x-2">
                        {service.is_running ? (
                          <>
                            <button
                              onClick={() => handleRestart(service.id)}
                              disabled={
                                actionLoading === `${service.id}-restart`
                              }
                              className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 disabled:opacity-50"
                            >
                              <ArrowPathIcon
                                className={`w-4 h-4 mr-1 ${actionLoading === `${service.id}-restart` ? "animate-spin" : ""}`}
                              />
                              Restart
                            </button>
                            <button
                              onClick={() => handleStop(service.id)}
                              disabled={actionLoading === `${service.id}-stop`}
                              className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 disabled:opacity-50"
                            >
                              <StopIcon className="w-4 h-4 mr-1" />
                              Stop
                            </button>
                          </>
                        ) : (
                          <button
                            onClick={() => handleStart(service.id)}
                            disabled={actionLoading === `${service.id}-start`}
                            className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200 disabled:opacity-50"
                          >
                            <PlayIcon className="w-4 h-4 mr-1" />
                            Start
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </CardBody>
      </Card>

      {/* Quick Actions */}
      <Card>
        <CardBody>
          <h3 className="text-lg font-semibold text-gray-900 mb-4">
            Quick Actions
          </h3>
          <div className="flex flex-wrap gap-3">
            <Button
              variant="secondary"
              onClick={async () => {
                const webServices = services.filter((s) =>
                  ["httpd", "nginx", "php-fpm"].includes(s.id),
                );
                for (const svc of webServices) {
                  if (svc.is_running) {
                    await handleRestart(svc.id);
                  }
                }
              }}
            >
              Restart All Web Services
            </Button>
            <Button
              variant="secondary"
              onClick={async () => {
                const mailServices = services.filter((s) =>
                  ["dovecot", "exim", "postfix"].includes(s.id),
                );
                for (const svc of mailServices) {
                  if (svc.is_running) {
                    await handleRestart(svc.id);
                  }
                }
              }}
            >
              Restart All Mail Services
            </Button>
            <Button
              variant="secondary"
              onClick={async () => {
                const dbServices = services.filter((s) =>
                  ["mariadb", "mysql", "postgresql", "redis"].includes(s.id),
                );
                for (const svc of dbServices) {
                  if (svc.is_running) {
                    await handleRestart(svc.id);
                  }
                }
              }}
            >
              Restart All Database Services
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
