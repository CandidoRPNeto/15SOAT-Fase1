output "cluster_name" {
  description = "Nome do cluster kind criado."
  value       = kind_cluster.workshop_os.name
}

output "kubeconfig_path" {
  description = "Caminho do kubeconfig gerado — passar para infra/database e usar com kubectl."
  value       = pathexpand(var.kubeconfig_path)
}

output "endpoint" {
  description = "Endpoint da API do Kubernetes do cluster criado."
  value       = kind_cluster.workshop_os.endpoint
}
