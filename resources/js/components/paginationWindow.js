export function pageWindow(current, total, width = 5) {
    if (total < 1) return [];
    const page = Math.max(1, Math.min(current, total));
    const start = Math.max(1, Math.min(page - Math.floor(width / 2), total - width + 1));
    const end = Math.min(total, start + width - 1);
    const result = [];
    if (start > 1) result.push(1);
    if (start > 2) result.push('before');
    for (let i = start; i <= end; i++) result.push(i);
    if (end < total - 1) result.push('after');
    if (end < total) result.push(total);
    return result;
}
