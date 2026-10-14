export function setPermission(nodes, selected, id, checked, includeChildren = true) {
    const result = new Set(selected);
    const byId = new Map(nodes.map(node => [node.id, node]));
    if (!byId.has(id)) return result;
    const descendants = [id];
    if (includeChildren) {
        for (let index = 0; index < descendants.length; index++) {
            for (const node of nodes) {
                if (node.parent === descendants[index] && !descendants.includes(node.id)) descendants.push(node.id);
            }
        }
    }
    for (const descendant of descendants) checked ? result.add(descendant) : result.delete(descendant);
    const seen = new Set([id]);
    let parent = byId.get(id).parent;
    while (byId.has(parent) && !seen.has(parent)) {
        seen.add(parent);
        if (checked || nodes.some(node => node.parent === parent && result.has(node.id))) result.add(parent);
        else result.delete(parent);
        parent = byId.get(parent).parent;
    }
    return result;
}

export function isPermissionVisible(nodes, id) {
    const byId = new Map(nodes.map(node => [node.id, node]));
    const seen = new Set([id]);
    let parent = byId.get(id)?.parent;
    while (byId.has(parent)) {
        if (seen.has(parent) || !byId.get(parent).expanded) return false;
        seen.add(parent);
        parent = byId.get(parent).parent;
    }
    return true;
}
