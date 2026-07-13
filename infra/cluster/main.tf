# Provisiona o cluster local (kind) usado pelas fases seguintes
# (infra/database e os manifestos em /k8s). Só o path local é implementado
# nesta fase — ver docs/specs/fase2/plan.md §3.3 sobre a troca para cloud.
resource "kind_cluster" "workshop_os" {
  name            = var.cluster_name
  node_image      = var.node_image
  wait_for_ready  = true
  kubeconfig_path = pathexpand(var.kubeconfig_path)

  kind_config {
    kind        = "Cluster"
    api_version = "kind.x-k8s.io/v1alpha4"

    node {
      role = "control-plane"

      extra_port_mappings {
        container_port = var.api_node_port
        host_port      = var.api_node_port
      }
    }

    node {
      role = "worker"
    }
  }
}
