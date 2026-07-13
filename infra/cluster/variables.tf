variable "cluster_name" {
  description = "Nome do cluster kind provisionado localmente."
  type        = string
  default     = "workshop-os"
}

variable "node_image" {
  description = "Imagem do node kind (define a versão do Kubernetes do cluster)."
  type        = string
  default     = "kindest/node:v1.29.2"
}

variable "kubeconfig_path" {
  description = "Onde escrever o kubeconfig deste cluster. Consumido pelo módulo infra/database e pelo kubectl."
  type        = string
  default     = "~/.kube/workshop-os-config"
}

variable "api_node_port" {
  description = "Porta do host mapeada para o NodePort da API (deve bater com k8s/service.yaml)."
  type        = number
  default     = 30080
}
