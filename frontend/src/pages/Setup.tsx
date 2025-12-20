import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import Button from "../components/common/Button";
import Input, { PasswordStrength } from "../components/common/Input";
import Badge from "../components/common/Badge";
import { Card, CardBody } from "../components/common/Card";
import toast from "react-hot-toast";
import {
  CheckCircleIcon,
  XCircleIcon,
  ServerIcon,
  UserIcon,
  Cog6ToothIcon,
  RocketLaunchIcon,
  ArrowLeftIcon,
  ArrowRightIcon,
  ShieldCheckIcon,
} from "@heroicons/react/24/outline";
import setupApi, { SetupStatus, Requirement } from "../api/setup";

const steps = [
  { id: "welcome", name: "Welcome", icon: RocketLaunchIcon },
  { id: "requirements", name: "Requirements", icon: ServerIcon },
  { id: "admin", name: "Admin Account", icon: UserIcon },
  { id: "server", name: "Server Config", icon: Cog6ToothIcon },
  { id: "complete", name: "Complete", icon: CheckCircleIcon },
];

export default function Setup() {
  const navigate = useNavigate();

  const [currentStep, setCurrentStep] = useState(0);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [setupStatus, setSetupStatus] = useState<SetupStatus | null>(null);
  const [requirements, setRequirements] = useState<Requirement[]>([]);
  const [allRequirementsMet, setAllRequirementsMet] = useState(false);

  // Form data
  const [formData, setFormData] = useState({
    admin_username: "",
    admin_email: "",
    admin_password: "",
    admin_password_confirmation: "",
    server_hostname: "",
    server_ip: "",
    nameservers: ["", "", "", ""],
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Check setup status on mount
  useEffect(() => {
    checkSetupStatus();
  }, []);

  const checkSetupStatus = async () => {
    try {
      const status = await setupApi.getStatus();
      setSetupStatus(status);
      setRequirements(status.requirements);
      setAllRequirementsMet(status.requirements.every((r) => r.status));

      // If setup not required, redirect to login
      if (!status.setup_required) {
        navigate("/login");
        return;
      }
    } catch (error) {
      toast.error("Failed to check setup status");
    } finally {
      setLoading(false);
    }
  };

  const validateStep = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (currentStep === 2) {
      // Admin account validation
      if (!formData.admin_username || formData.admin_username.length < 3) {
        newErrors.admin_username = "Username must be at least 3 characters";
      } else if (!/^[a-z0-9]+$/.test(formData.admin_username)) {
        newErrors.admin_username =
          "Username can only contain lowercase letters and numbers";
      }

      if (
        !formData.admin_email ||
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.admin_email)
      ) {
        newErrors.admin_email = "Please enter a valid email address";
      }

      if (!formData.admin_password || formData.admin_password.length < 8) {
        newErrors.admin_password = "Password must be at least 8 characters";
      }

      if (formData.admin_password !== formData.admin_password_confirmation) {
        newErrors.admin_password_confirmation = "Passwords do not match";
      }
    }

    if (currentStep === 3) {
      // Server config validation (optional fields, but validate format if provided)
      if (
        formData.server_ip &&
        !/^(\d{1,3}\.){3}\d{1,3}$/.test(formData.server_ip)
      ) {
        newErrors.server_ip = "Please enter a valid IP address";
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleNext = () => {
    if (currentStep === 1 && !allRequirementsMet) {
      toast.error("Please ensure all requirements are met before continuing");
      return;
    }

    if (!validateStep()) return;

    if (currentStep < steps.length - 1) {
      setCurrentStep(currentStep + 1);
    }
  };

  const handleBack = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
    }
  };

  const handleComplete = async () => {
    if (!validateStep()) return;

    setSubmitting(true);
    try {
      const response = await setupApi.initialize({
        admin_username: formData.admin_username,
        admin_email: formData.admin_email,
        admin_password: formData.admin_password,
        admin_password_confirmation: formData.admin_password_confirmation,
        server_hostname: formData.server_hostname || undefined,
        server_ip: formData.server_ip || undefined,
        nameservers:
          formData.nameservers.filter((ns) => ns.trim()) || undefined,
      });

      if (response.success && response.token) {
        // Store token and user
        localStorage.setItem("token", response.token);
        localStorage.setItem("user", JSON.stringify(response.user));

        toast.success("Setup completed successfully!");
        setCurrentStep(4); // Go to complete step

        // Redirect after a delay
        setTimeout(() => {
          navigate("/admin");
        }, 2000);
      }
    } catch (error: any) {
      const message = error.response?.data?.message || "Setup failed";
      toast.error(message);

      if (error.response?.data?.errors) {
        const apiErrors: Record<string, string> = {};
        Object.entries(error.response.data.errors).forEach(([key, value]) => {
          apiErrors[key] = Array.isArray(value) ? value[0] : String(value);
        });
        setErrors(apiErrors);
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-white mx-auto" />
          <p className="mt-4 text-white">Checking setup status...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 py-12 px-4">
      <div className="max-w-3xl mx-auto">
        {/* Logo/Brand */}
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-white">FreePanel</h1>
          <p className="text-primary-200 mt-2">Initial Server Setup</p>
        </div>

        {/* Progress Steps */}
        <div className="mb-8">
          <div className="flex items-center justify-center">
            {steps.map((step, index) => (
              <div key={step.id} className="flex items-center">
                <div
                  className={`flex items-center justify-center w-10 h-10 rounded-full transition-colors ${
                    index < currentStep
                      ? "bg-green-500 text-white"
                      : index === currentStep
                        ? "bg-white text-primary-600"
                        : "bg-primary-500/30 text-primary-300"
                  }`}
                >
                  {index < currentStep ? (
                    <CheckCircleIcon className="w-6 h-6" />
                  ) : (
                    <step.icon className="w-5 h-5" />
                  )}
                </div>
                {index < steps.length - 1 && (
                  <div
                    className={`w-12 sm:w-20 h-1 mx-2 rounded transition-colors ${
                      index < currentStep ? "bg-green-500" : "bg-primary-500/30"
                    }`}
                  />
                )}
              </div>
            ))}
          </div>
          <div className="flex justify-between mt-2 px-2">
            {steps.map((step, index) => (
              <span
                key={step.id}
                className={`text-xs ${
                  index <= currentStep ? "text-white" : "text-primary-300"
                }`}
              >
                {step.name}
              </span>
            ))}
          </div>
        </div>

        {/* Step Content */}
        <Card className="shadow-2xl">
          <CardBody className="p-8">
            {/* Step 0: Welcome */}
            {currentStep === 0 && (
              <div className="text-center">
                <div className="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
                  <RocketLaunchIcon className="w-10 h-10 text-primary-600" />
                </div>
                <h2 className="text-2xl font-bold text-gray-900 mb-4">
                  Welcome to FreePanel
                </h2>
                <p className="text-gray-600 mb-6 max-w-md mx-auto">
                  Thank you for choosing FreePanel! This wizard will help you
                  configure your server and create your administrator account.
                </p>
                <div className="bg-blue-50 rounded-lg p-4 text-left max-w-md mx-auto">
                  <h3 className="font-semibold text-blue-900 mb-2">
                    Setup includes:
                  </h3>
                  <ul className="text-sm text-blue-700 space-y-1">
                    <li className="flex items-center gap-2">
                      <CheckCircleIcon className="w-4 h-4 text-blue-500" />
                      System requirements check
                    </li>
                    <li className="flex items-center gap-2">
                      <CheckCircleIcon className="w-4 h-4 text-blue-500" />
                      Administrator account creation
                    </li>
                    <li className="flex items-center gap-2">
                      <CheckCircleIcon className="w-4 h-4 text-blue-500" />
                      Server configuration (optional)
                    </li>
                  </ul>
                </div>
                {setupStatus && (
                  <p className="mt-6 text-sm text-gray-500">
                    Version {setupStatus.version}
                  </p>
                )}
              </div>
            )}

            {/* Step 1: Requirements */}
            {currentStep === 1 && (
              <div>
                <div className="text-center mb-6">
                  <div className="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <ServerIcon className="w-8 h-8 text-primary-600" />
                  </div>
                  <h2 className="text-xl font-bold text-gray-900">
                    System Requirements
                  </h2>
                  <p className="text-gray-600 mt-1">
                    Checking if your server meets all requirements
                  </p>
                </div>

                <div className="space-y-3">
                  {requirements.map((req, index) => (
                    <div
                      key={index}
                      className={`flex items-center justify-between p-3 rounded-lg ${
                        req.status ? "bg-green-50" : "bg-red-50"
                      }`}
                    >
                      <div className="flex items-center gap-3">
                        {req.status ? (
                          <CheckCircleIcon className="w-5 h-5 text-green-500" />
                        ) : (
                          <XCircleIcon className="w-5 h-5 text-red-500" />
                        )}
                        <div>
                          <p className="font-medium text-gray-900">
                            {req.name}
                          </p>
                          <p className="text-sm text-gray-500">
                            Required: {req.required}
                          </p>
                        </div>
                      </div>
                      <Badge variant={req.status ? "success" : "danger"}>
                        {req.current}
                      </Badge>
                    </div>
                  ))}
                </div>

                {!allRequirementsMet && (
                  <div className="mt-6 p-4 bg-yellow-50 rounded-lg">
                    <p className="text-sm text-yellow-800">
                      <strong>Note:</strong> Some requirements are not met.
                      Please install the missing dependencies before continuing.
                    </p>
                  </div>
                )}
              </div>
            )}

            {/* Step 2: Admin Account */}
            {currentStep === 2 && (
              <div>
                <div className="text-center mb-6">
                  <div className="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <UserIcon className="w-8 h-8 text-primary-600" />
                  </div>
                  <h2 className="text-xl font-bold text-gray-900">
                    Administrator Account
                  </h2>
                  <p className="text-gray-600 mt-1">
                    Create your server administrator account
                  </p>
                </div>

                <div className="space-y-4 max-w-md mx-auto">
                  <Input
                    label="Username"
                    placeholder="admin"
                    value={formData.admin_username}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        admin_username: e.target.value
                          .toLowerCase()
                          .replace(/[^a-z0-9]/g, ""),
                      })
                    }
                    error={errors.admin_username}
                    hint="3-16 lowercase letters and numbers"
                    maxLength={16}
                  />

                  <Input
                    label="Email Address"
                    type="email"
                    placeholder="admin@example.com"
                    value={formData.admin_email}
                    onChange={(e) =>
                      setFormData({ ...formData, admin_email: e.target.value })
                    }
                    error={errors.admin_email}
                  />

                  <div>
                    <Input
                      label="Password"
                      type="password"
                      placeholder="Minimum 8 characters"
                      value={formData.admin_password}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          admin_password: e.target.value,
                        })
                      }
                      error={errors.admin_password}
                      showPasswordToggle
                    />
                    <PasswordStrength password={formData.admin_password} />
                  </div>

                  <Input
                    label="Confirm Password"
                    type="password"
                    placeholder="Re-enter password"
                    value={formData.admin_password_confirmation}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        admin_password_confirmation: e.target.value,
                      })
                    }
                    error={errors.admin_password_confirmation}
                    showPasswordToggle
                  />
                </div>
              </div>
            )}

            {/* Step 3: Server Configuration */}
            {currentStep === 3 && (
              <div>
                <div className="text-center mb-6">
                  <div className="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Cog6ToothIcon className="w-8 h-8 text-primary-600" />
                  </div>
                  <h2 className="text-xl font-bold text-gray-900">
                    Server Configuration
                  </h2>
                  <p className="text-gray-600 mt-1">
                    Configure your server settings (optional)
                  </p>
                </div>

                <div className="space-y-4 max-w-md mx-auto">
                  <Input
                    label="Server Hostname"
                    placeholder="server.example.com"
                    value={formData.server_hostname}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        server_hostname: e.target.value,
                      })
                    }
                    hint="The hostname for this server"
                  />

                  <Input
                    label="Server IP Address"
                    placeholder="192.168.1.100"
                    value={formData.server_ip}
                    onChange={(e) =>
                      setFormData({ ...formData, server_ip: e.target.value })
                    }
                    error={errors.server_ip}
                    hint="Primary IP address of this server"
                  />

                  <div>
                    <label className="label">Nameservers (Optional)</label>
                    <div className="space-y-2">
                      {formData.nameservers.map((ns, index) => (
                        <input
                          key={index}
                          type="text"
                          className="input"
                          placeholder={`ns${index + 1}.example.com`}
                          value={ns}
                          onChange={(e) => {
                            const newNs = [...formData.nameservers];
                            newNs[index] = e.target.value;
                            setFormData({ ...formData, nameservers: newNs });
                          }}
                        />
                      ))}
                    </div>
                    <p className="text-sm text-gray-500 mt-1">
                      Enter your DNS nameservers (up to 4)
                    </p>
                  </div>
                </div>

                <div className="mt-6 p-4 bg-gray-50 rounded-lg max-w-md mx-auto">
                  <p className="text-sm text-gray-600">
                    <strong>Note:</strong> These settings can be changed later
                    from the admin panel. You can skip this step if you're not
                    sure.
                  </p>
                </div>
              </div>
            )}

            {/* Step 4: Complete */}
            {currentStep === 4 && (
              <div className="text-center">
                <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                  <CheckCircleIcon className="w-10 h-10 text-green-600" />
                </div>
                <h2 className="text-2xl font-bold text-gray-900 mb-4">
                  Setup Complete!
                </h2>
                <p className="text-gray-600 mb-6">
                  FreePanel has been successfully configured. You will be
                  redirected to the admin dashboard.
                </p>
                <div className="bg-green-50 rounded-lg p-4 max-w-md mx-auto">
                  <div className="flex items-center justify-center gap-2 text-green-700">
                    <ShieldCheckIcon className="w-5 h-5" />
                    <span className="font-medium">
                      Logged in as {formData.admin_username}
                    </span>
                  </div>
                </div>
              </div>
            )}
          </CardBody>

          {/* Navigation Buttons */}
          {currentStep < 4 && (
            <div className="px-8 pb-8 flex justify-between">
              <Button
                variant="secondary"
                onClick={handleBack}
                disabled={currentStep === 0}
              >
                <ArrowLeftIcon className="w-4 h-4 mr-2" />
                Back
              </Button>

              {currentStep === 3 ? (
                <Button
                  variant="primary"
                  onClick={handleComplete}
                  isLoading={submitting}
                >
                  Complete Setup
                  <CheckCircleIcon className="w-4 h-4 ml-2" />
                </Button>
              ) : (
                <Button variant="primary" onClick={handleNext}>
                  Continue
                  <ArrowRightIcon className="w-4 h-4 ml-2" />
                </Button>
              )}
            </div>
          )}
        </Card>

        {/* Footer */}
        <p className="text-center text-primary-200 text-sm mt-8">
          FreePanel - Open Source Hosting Control Panel
        </p>
      </div>
    </div>
  );
}
