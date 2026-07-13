variable "kubeconfig_path" {
  description = "Kubeconfig do cluster provisionado por infra/cluster (ver output kubeconfig_path)."
  type        = string
  default     = "~/.kube/workshop-os-config"
}
